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
 * Backup structure for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Defines the backup structure.
 */
class backup_watchcomplete_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $wc = new backup_nested_element('watchcomplete', ['id'], [
            'name', 'intro', 'introformat', 'videosource', 'videourl', 'duration', 'threshold',
            'preventskipping', 'resume', 'completionwatched', 'timecreated', 'timemodified',
        ]);
        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], [
            'userid', 'segments', 'covered', 'percent', 'lastposition', 'completed',
            'timecreated', 'timemodified',
        ]);
        $wc->add_child($progresses);
        $progresses->add_child($progress);

        $wc->set_source_table('watchcomplete', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $progress->set_source_table('watchcomplete_progress', ['watchcompleteid' => backup::VAR_PARENTID]);
            $progress->annotate_ids('user', 'userid');
        }
        $wc->annotate_files('mod_watchcomplete', 'content', null);

        return $this->prepare_activity_structure($wc);
    }
}
