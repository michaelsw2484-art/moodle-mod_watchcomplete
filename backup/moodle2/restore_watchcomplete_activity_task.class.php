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
 * Restore task for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/watchcomplete/backup/moodle2/restore_watchcomplete_stepslib.php');

/**
 * Restore task.
 */
class restore_watchcomplete_activity_task extends restore_activity_task {
    /**
     * No module-specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Define the restore step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_watchcomplete_activity_structure_step('watchcomplete_structure', 'watchcomplete.xml'));
    }

    /**
     * Content decode rules.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('watchcomplete', ['intro'], 'watchcomplete')];
    }

    /**
     * Decode rules for links.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [new restore_decode_rule('WATCHCOMPLETEVIEWBYID', '/mod/watchcomplete/view.php?id=$1', 'course_module')];
    }
}
