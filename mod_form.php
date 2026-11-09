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
 * Activity settings form for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/watchcomplete/lib.php');

use mod_watchcomplete\local\video;

/**
 * Teacher-facing settings form.
 */
class mod_watchcomplete_mod_form extends moodleform_mod {
    /** @var int[] Preset threshold percentages. */
    private const PRESETS = [50, 75, 90, 95, 100];

    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'videohdr', get_string('videosettings', 'mod_watchcomplete'));
        $mform->addElement('select', 'videosource', get_string('videosource', 'mod_watchcomplete'), [
            'youtube' => get_string('source_youtube', 'mod_watchcomplete'),
            'vimeo' => get_string('source_vimeo', 'mod_watchcomplete'),
            'mp4' => get_string('source_mp4', 'mod_watchcomplete'),
        ]);
        $mform->setDefault('videosource', 'youtube');
        $mform->addHelpButton('videosource', 'videosource', 'mod_watchcomplete');

        $mform->addElement('text', 'videourl', get_string('videourl', 'mod_watchcomplete'), ['size' => '80']);
        $mform->setType('videourl', PARAM_URL);
        $mform->addHelpButton('videourl', 'videourl', 'mod_watchcomplete');

        $mform->addElement(
            'filemanager',
            'videofile',
            get_string('videofile', 'mod_watchcomplete'),
            null,
            watchcomplete_file_options()
        );
        $mform->addHelpButton('videofile', 'videofile', 'mod_watchcomplete');
        $mform->hideIf('videofile', 'videosource', 'neq', 'mp4');

        $mform->addElement('text', 'duration', get_string('duration', 'mod_watchcomplete'), ['size' => '8']);
        $mform->setType('duration', PARAM_INT);
        $mform->setDefault('duration', 0);
        $mform->addHelpButton('duration', 'duration', 'mod_watchcomplete');

        $mform->addElement('header', 'completionhdr', get_string('completionsettings', 'mod_watchcomplete'));
        $options = [];
        foreach (self::PRESETS as $preset) {
            $options[$preset] = get_string('thresholdoption', 'mod_watchcomplete', $preset);
        }
        $options[0] = get_string('customthreshold', 'mod_watchcomplete');
        $mform->addElement('select', 'threshold', get_string('threshold', 'mod_watchcomplete'), $options);
        $mform->setDefault('threshold', 90);
        $mform->addHelpButton('threshold', 'threshold', 'mod_watchcomplete');

        $mform->addElement(
            'text',
            'customthreshold',
            get_string('customthresholdvalue', 'mod_watchcomplete'),
            ['size' => '4']
        );
        $mform->setType('customthreshold', PARAM_INT);
        $mform->hideIf('customthreshold', 'threshold', 'neq', 0);

        $mform->addElement(
            'advcheckbox',
            'preventskipping',
            get_string('preventskipping', 'mod_watchcomplete'),
            get_string('enableoption', 'mod_watchcomplete')
        );
        $mform->setDefault('preventskipping', 1);
        $mform->addHelpButton('preventskipping', 'preventskipping', 'mod_watchcomplete');

        $mform->addElement(
            'advcheckbox',
            'resume',
            get_string('resume', 'mod_watchcomplete'),
            get_string('enableoption', 'mod_watchcomplete')
        );
        $mform->setDefault('resume', 1);
        $mform->addHelpButton('resume', 'resume', 'mod_watchcomplete');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add the custom "watch to threshold" completion rule to the completion section.
     *
     * @return string[] Names of the rule elements added.
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $mform->addElement(
            'advcheckbox',
            'completionwatched',
            get_string('completionwatched', 'mod_watchcomplete'),
            get_string('completionwatchedlabel', 'mod_watchcomplete')
        );
        $mform->addHelpButton('completionwatched', 'completionwatched', 'mod_watchcomplete');
        return ['completionwatched'];
    }

    /**
     * Whether the custom completion rule is switched on.
     *
     * @param array $data Submitted form data.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionwatched']);
    }

    /**
     * Server-side validation of the submitted settings.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $source = $data['videosource'] ?? 'youtube';
        $url = trim((string) ($data['videourl'] ?? ''));

        if ($source === 'mp4') {
            $hasfile = !empty($data['videofile'])
                && count(file_get_drafarea_files($data['videofile'])->list) > 0;
            if (!$hasfile && $url === '') {
                $errors['videofile'] = get_string('err_mp4required', 'mod_watchcomplete');
            }
        } else if ($url === '') {
            $errors['videourl'] = get_string('err_urlrequired', 'mod_watchcomplete');
        }

        if ($url !== '' && video::parse($source, $url) === null) {
            $errors['videourl'] = get_string('err_invalidurl', 'mod_watchcomplete');
        }

        $duration = (int) ($data['duration'] ?? 0);
        if ($duration < 0 || $duration > \mod_watchcomplete\local\progress::MAX_DURATION) {
            $errors['duration'] = get_string('err_duration', 'mod_watchcomplete');
        }

        if ((int) ($data['threshold'] ?? 90) === 0) {
            $custom = (int) ($data['customthreshold'] ?? 0);
            if ($custom < 1 || $custom > 100) {
                $errors['customthreshold'] = get_string('err_customthreshold', 'mod_watchcomplete');
            }
        }

        return $errors;
    }

    /**
     * Prepare default values, including the draft file area and custom threshold.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        $draftitemid = file_get_submitted_draft_itemid('videofile');
        file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'mod_watchcomplete',
            'content',
            0,
            watchcomplete_file_options()
        );
        $defaultvalues['videofile'] = $draftitemid;

        $threshold = (int) ($defaultvalues['threshold'] ?? 90);
        if (!in_array($threshold, self::PRESETS, true)) {
            $defaultvalues['customthreshold'] = $threshold;
            $defaultvalues['threshold'] = 0;
        }
    }
}
