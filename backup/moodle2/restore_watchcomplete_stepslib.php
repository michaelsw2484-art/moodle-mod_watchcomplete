<?php
// This file is part of Moodle - https://moodle.org/
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
// GNU GPL v3 or later. See <http://www.gnu.org/licenses/>.

/**
 * Restore structure for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Restores the activity and, when user data is included, its progress.
 */
class restore_watchcomplete_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the restore paths.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [new restore_path_element('watchcomplete', '/activity/watchcomplete')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('watchcomplete_progress', '/activity/watchcomplete/progresses/progress');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity record.
     *
     * @param stdClass $data
     */
    protected function process_watchcomplete($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        unset($data->id);

        $newid = $DB->insert_record('watchcomplete', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore a student's progress.
     *
     * @param stdClass $data
     */
    protected function process_watchcomplete_progress($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->watchcompleteid = $this->get_new_parentid('watchcomplete');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('watchcomplete_progress', $data);
    }

    /**
     * Restore the uploaded video files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_watchcomplete', 'content', null);
    }
}
