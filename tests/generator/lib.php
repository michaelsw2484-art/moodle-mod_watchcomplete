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
 * Test data generator for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Creates WatchComplete activities for unit tests.
 */
class mod_watchcomplete_generator extends testing_module_generator {
    /**
     * Create an activity instance with sensible test defaults.
     *
     * @param array|stdClass|null $record Values that override the defaults.
     * @param array|null $options Module options, such as the course module settings.
     * @return stdClass The created activity record, with cmid set.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;
        $defaults = [
            'name' => 'Test WatchComplete',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'videosource' => 'youtube',
            'videourl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'duration' => 0,
            'threshold' => 90,
            'preventskipping' => 0,
            'resume' => 1,
            'completionwatched' => 0,
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($record->$key)) {
                $record->$key = $value;
            }
        }
        return parent::create_instance($record, (array) $options);
    }
}
