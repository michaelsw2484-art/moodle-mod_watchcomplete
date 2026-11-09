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
 * Backup task for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/watchcomplete/backup/moodle2/backup_watchcomplete_stepslib.php');

/**
 * Backup task.
 */
class backup_watchcomplete_activity_task extends backup_activity_task {
    /**
     * No module-specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Define the backup step.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_watchcomplete_activity_structure_step('watchcomplete_structure', 'watchcomplete.xml'));
    }

    /**
     * Rewrite links to this activity in backed-up content.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $pattern = '/(' . $base . '\/mod\/watchcomplete\/index\.php\?id=)([0-9]+)/';
        return preg_replace($pattern, '$@WATCHCOMPLETEINDEX*$2@$', $content);
    }
}
