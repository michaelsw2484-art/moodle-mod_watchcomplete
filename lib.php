<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_watchcomplete\local\progress;
use mod_watchcomplete\local\video;

/**
 * Features this module supports.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed
 */
function watchcomplete_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Tell Moodle which custom completion rules are active for an activity.
 *
 * Moodle reads customdata['customcompletionrules'] to decide whether the "Watch to threshold" rule applies.
 *
 * @param stdClass $coursemodule The course module record.
 * @return cached_cm_info|false
 */
function watchcomplete_get_coursemodule_info($coursemodule) {
    global $DB;

    $wc = $DB->get_record(
        'watchcomplete',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, threshold, completionwatched'
    );
    if (!$wc) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $wc->name;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('watchcomplete', $wc, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC && !empty($wc->completionwatched)) {
        $result->customdata['customcompletionrules'][\mod_watchcomplete\completion\custom_completion::RULE_WATCHED] = 1;
    }
    return $result;
}

/**
 * Options for the video file manager.
 *
 * @return array
 */
function watchcomplete_file_options(): array {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');
    return [
        'subdirs' => 0,
        'maxfiles' => 1,
        'maxbytes' => get_max_upload_file_size($CFG->maxbytes),
        'accepted_types' => ['.mp4', '.webm', '.m4v'],
    ];
}

/**
 * Normalise submitted form data before it is stored.
 *
 * @param stdClass $data
 * @return stdClass
 */
function watchcomplete_prepare_data(stdClass $data): stdClass {
    $data->videosource = in_array($data->videosource ?? '', video::SOURCES, true) ? $data->videosource : 'youtube';

    $url = trim((string) ($data->videourl ?? ''));
    $data->videourl = video::parse($data->videosource, $url) !== null ? clean_param($url, PARAM_URL) : '';

    $threshold = (int) ($data->threshold ?? 90);
    if ($threshold === 0) {
        // Zero is the "custom" option in the form.
        $threshold = (int) ($data->customthreshold ?? 90);
    }
    $data->threshold = max(1, min(100, $threshold));

    $data->duration = max(0, min(progress::MAX_DURATION, (int) ($data->duration ?? 0)));
    $data->preventskipping = empty($data->preventskipping) ? 0 : 1;
    $data->resume = empty($data->resume) ? 0 : 1;
    $data->completionwatched = empty($data->completionwatched) ? 0 : 1;

    return $data;
}

/**
 * Save the uploaded MP4 or delete stored files if another source is selected.
 *
 * @param stdClass $data
 * @param context_module $context
 */
function watchcomplete_save_video_file(stdClass $data, context_module $context): void {
    $fs = get_file_storage();
    if ($data->videosource !== 'mp4') {
        $fs->delete_area_files($context->id, 'mod_watchcomplete', 'content');
        return;
    }
    if (!empty($data->videofile)) {
        file_save_draft_area_files(
            $data->videofile,
            $context->id,
            'mod_watchcomplete',
            'content',
            0,
            watchcomplete_file_options()
        );
    }
}

/**
 * Create an activity instance.
 *
 * @param stdClass $data
 * @param mixed $mform
 * @return int
 */
function watchcomplete_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    $data = watchcomplete_prepare_data($data);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $id = $DB->insert_record('watchcomplete', $data);

    watchcomplete_save_video_file($data, context_module::instance($data->coursemodule));
    return $id;
}

/**
 * A key that identifies the media an activity plays: its source, URL and, for uploads, the file hash.
 *
 * @param stdClass $wc Instance record or submitted data with videosource and videourl.
 * @param context_module $context
 * @return string
 */
function watchcomplete_media_key(stdClass $wc, context_module $context): string {
    $key = $wc->videosource . '|' . $wc->videourl;
    if ($wc->videosource === 'mp4') {
        $files = get_file_storage()->get_area_files($context->id, 'mod_watchcomplete', 'content', 0, 'id DESC', false);
        if ($file = reset($files)) {
            $key .= '|' . $file->get_contenthash();
        }
    }
    return $key;
}

/**
 * Update an activity instance.
 *
 * If the media changes, the stored length and student progress belong to the old media, so both are reset.
 *
 * @param stdClass $data
 * @param mixed $mform
 * @return bool
 */
function watchcomplete_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $old = $DB->get_record('watchcomplete', ['id' => $data->instance], '*', MUST_EXIST);
    $context = context_module::instance($data->coursemodule);
    $oldkey = watchcomplete_media_key($old, $context);

    $data = watchcomplete_prepare_data($data);
    $data->id = $data->instance;
    $data->timemodified = time();

    // Save files first so the new media key reflects any replaced upload.
    watchcomplete_save_video_file($data, $context);
    $newkey = watchcomplete_media_key($data, $context);

    if ($newkey !== $oldkey) {
        // The form pre-fills the old length. Keep it only if the teacher changed it.
        if ((int) $data->duration === (int) $old->duration) {
            $data->duration = 0;
        }
        $DB->delete_records('watchcomplete_progress', ['watchcompleteid' => $data->id]);
    }

    $DB->update_record('watchcomplete', $data);

    if ((int) $old->duration !== (int) $data->duration || (int) $old->threshold !== (int) $data->threshold) {
        watchcomplete_recalculate_progress($data);
    }
    return true;
}

/**
 * Recalculate stored percentages after a duration or threshold change.
 *
 * Completion already awarded is kept, so a teacher raising the threshold does not revoke it.
 *
 * @param stdClass $wc The updated instance record.
 */
function watchcomplete_recalculate_progress(stdClass $wc): void {
    global $DB;

    foreach ($DB->get_records('watchcomplete_progress', ['watchcompleteid' => $wc->id]) as $p) {
        $summary = progress::summarise(progress::parse($p->segments), (int) $wc->duration, (int) $wc->threshold);
        $p->covered = $summary['covered'];
        $p->percent = $summary['percent'];
        $p->completed = ((int) $p->completed === 1 || $summary['completed']) ? 1 : 0;
        $p->timemodified = time();
        $DB->update_record('watchcomplete_progress', $p);
    }
}

/**
 * Delete an activity instance and its data.
 *
 * @param int $id
 * @return bool
 */
function watchcomplete_delete_instance(int $id): bool {
    global $DB;

    if (!$DB->record_exists('watchcomplete', ['id' => $id])) {
        return false;
    }
    $cm = get_coursemodule_from_instance('watchcomplete', $id);
    $DB->delete_records('watchcomplete_progress', ['watchcompleteid' => $id]);
    $DB->delete_records('watchcomplete', ['id' => $id]);
    if ($cm) {
        get_file_storage()->delete_area_files(context_module::instance($cm->id)->id, 'mod_watchcomplete');
    }
    return true;
}

/**
 * Serve files uploaded to this activity.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function mod_watchcomplete_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== 'content') {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/watchcomplete:view', $context);

    $itemid = (int) array_shift($args);
    if ($itemid !== 0) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = get_file_storage()->get_file($context->id, 'mod_watchcomplete', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Add the WatchComplete section to the course reset form. Core finds this by the module name, watchcomplete.
 *
 * Its presence is what makes Moodle offer this module in course reset.
 *
 * @param MoodleQuickForm $mform
 */
function watchcomplete_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'watchcompleteheader', get_string('modulenameplural', 'mod_watchcomplete'));
    $mform->addElement(
        'advcheckbox',
        'reset_watchcomplete_progress',
        get_string('resetprogress', 'mod_watchcomplete')
    );
    $mform->setDefault('reset_watchcomplete_progress', 1);
}

/**
 * Default values for the reset form.
 *
 * @param stdClass $course
 * @return array
 */
function watchcomplete_reset_course_form_defaults($course): array {
    return ['reset_watchcomplete_progress' => 1];
}

/**
 * Clear student progress and completion for every WatchComplete activity in a course.
 *
 * @param stdClass $data Reset options from the course reset form.
 * @return array Status rows for the reset summary.
 */
function watchcomplete_reset_userdata($data): array {
    global $DB;

    $status = [];
    if (empty($data->reset_watchcomplete_progress)) {
        return $status;
    }

    $instances = $DB->get_records('watchcomplete', ['course' => $data->courseid], '', 'id');
    if ($instances) {
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($instances), SQL_PARAMS_NAMED);
        $DB->delete_records_select('watchcomplete_progress', "watchcompleteid $insql", $inparams);

        // Clear Moodle's completion state too, so reset doesn't leave wrong ticks behind.
        $course = get_course($data->courseid);
        $completion = new \completion_info($course);
        foreach (get_fast_modinfo($course)->get_instances_of('watchcomplete') as $cm) {
            $completion->reset_all_state($cm);
        }
    }

    $status[] = [
        'component' => get_string('modulenameplural', 'mod_watchcomplete'),
        'item' => get_string('resetprogress', 'mod_watchcomplete'),
        'error' => false,
    ];
    return $status;
}
