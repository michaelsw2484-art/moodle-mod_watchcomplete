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
 * Student view of a WatchComplete activity.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_watchcomplete\local\video;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'watchcomplete');
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/watchcomplete:view', $context);
$wc = $DB->get_record('watchcomplete', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/watchcomplete/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($wc->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);

// Resolve the playable source.
$url = '';
$parsed = null;
if ($wc->videosource === 'mp4') {
    $files = get_file_storage()->get_area_files($context->id, 'mod_watchcomplete', 'content', 0, 'id DESC', false);
    if ($file = reset($files)) {
        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_watchcomplete',
            'content',
            0,
            '/',
            $file->get_filename()
        )->out(false);
    } else {
        $url = $wc->videourl;
    }
    $parsed = $file ? ['source' => 'mp4', 'url' => $url] : video::parse('mp4', $url);
} else {
    $parsed = video::parse($wc->videosource, $wc->videourl);
}

$canreport = has_capability('mod/watchcomplete:viewreports', $context);
$cansubmit = has_capability('mod/watchcomplete:submit', $context);

$progress = $DB->get_record('watchcomplete_progress', ['watchcompleteid' => $wc->id, 'userid' => $USER->id]);
$resumeposition = $progress ? (int) $progress->lastposition : 0;
$showresume = $cansubmit && (bool) $wc->resume && $resumeposition > 0
    && !($progress && (int) $progress->completed === 1);

$event = \mod_watchcomplete\event\course_module_viewed::create([
    'objectid' => $wc->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('watchcomplete', $wc);
$event->trigger();

// Remember when the page was opened. The save service uses this to limit how much new progress one request can claim.
$SESSION->mod_watchcomplete_start[$cm->id] = time();

echo $OUTPUT->header();

if (!$parsed) {
    echo $OUTPUT->notification(get_string('nosource', 'mod_watchcomplete'), 'error');
} else {
    $template = [
        'cmid' => $cm->id,
        'ismp4' => $parsed['source'] === 'mp4',
        'isembed' => $parsed['source'] !== 'mp4',
        'url' => $parsed['source'] === 'mp4' ? $url : '',
        'videoid' => $parsed['id'] ?? '',
        'percent' => $progress ? (int) $progress->percent : 0,
        'completed' => $progress ? (int) $progress->completed === 1 : false,
        'completedmessage' => get_string('completedmessage', 'mod_watchcomplete', (int) $wc->threshold),
        'trackingdisabled' => !$cansubmit,
        'showresume' => $showresume,
        'resumeprompt' => $showresume ? get_string(
            'resumeprompt',
            'mod_watchcomplete',
            gmdate($resumeposition >= 3600 ? 'G:i:s' : 'i:s', $resumeposition)
        ) : '',
        'resumebutton' => get_string('resumebutton', 'mod_watchcomplete'),
        'restartbutton' => get_string('restartbutton', 'mod_watchcomplete'),
        'canreport' => $canreport,
        'reporturl' => (new moodle_url('/mod/watchcomplete/report.php', ['id' => $cm->id]))->out(false),
    ];
    echo $OUTPUT->render_from_template('mod_watchcomplete/player', $template);

    $config = [
        'cmid' => $cm->id,
        'source' => $parsed['source'],
        'videoid' => $parsed['id'] ?? '',
        'url' => $url,
        'duration' => (int) $wc->duration,
        'threshold' => (int) $wc->threshold,
        'preventskipping' => (bool) $wc->preventskipping,
        'resume' => (bool) $wc->resume,
        'segments' => $progress ? $progress->segments : '',
        'position' => $progress ? (int) $progress->lastposition : 0,
        'percent' => $template['percent'],
        'completed' => $template['completed'],
        'canSubmit' => $cansubmit,
        'skipMessage' => get_string('preventskipnotice', 'mod_watchcomplete'),
    ];
    $PAGE->requires->js_call_amd('mod_watchcomplete/player', 'init', [$config]);
}

echo $OUTPUT->footer();
