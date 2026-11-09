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
 * Custom completion rules for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_watchcomplete\completion;

use core_completion\activity_custom_completion;

/**
 * Reports the "Watch to threshold" rule to Moodle's completion engine.
 *
 * Moodle evaluates completion from these rules. Without this class, an activity with no other rules is
 * treated as complete for everyone, which is why the plugin previously marked students complete.
 */
class custom_completion extends activity_custom_completion {
    /** The only custom rule this module defines. */
    public const RULE_WATCHED = 'completionwatched';

    /**
     * Completion state of a rule for this user.
     *
     * @param string $rule
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;

        if ($rule !== self::RULE_WATCHED) {
            return COMPLETION_UNKNOWN;
        }
        $completed = $DB->get_field(
            'watchcomplete_progress',
            'completed',
            ['watchcompleteid' => $this->cm->instance, 'userid' => $this->userid]
        );
        return ((int) $completed === 1) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Custom rules this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [self::RULE_WATCHED];
    }

    /**
     * Descriptions shown to students, including the threshold from the activity.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;

        $threshold = (int) $DB->get_field('watchcomplete', 'threshold', ['id' => $this->cm->instance]);
        return [
            self::RULE_WATCHED => get_string('completionwatchedrule', 'mod_watchcomplete', $threshold),
        ];
    }

    /**
     * Display order of the rules.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [self::RULE_WATCHED];
    }
}
