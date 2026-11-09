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
 * External function: save a student's watched segments.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_watchcomplete\external;

use mod_watchcomplete\local\progress;
use mod_watchcomplete\event\video_completed;

/**
 * Accepts the segments played since the last save and returns the server's merged result.
 *
 * The server is the source of truth: the client's claimed completion is ignored, and completion is
 * calculated here from the stored segments and the activity's threshold.
 */
class update_progress extends \core_external\external_api {
    /**
     * Parameter definition.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'cmid' => new \core_external\external_value(PARAM_INT, 'Course module id'),
            'segments' => new \core_external\external_multiple_structure(
                new \core_external\external_single_structure([
                    'start' => new \core_external\external_value(PARAM_INT, 'Start of played range, in seconds'),
                    'end' => new \core_external\external_value(PARAM_INT, 'End of played range, in seconds'),
                ]),
                'Played ranges in playback order',
                VALUE_DEFAULT,
                []
            ),
            'position' => new \core_external\external_value(PARAM_INT, 'Current playback position, in seconds', VALUE_DEFAULT, 0),
            'duration' => new \core_external\external_value(
                PARAM_INT,
                'Media length reported by the player, in seconds',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Merge and store a student's progress.
     *
     * @param int $cmid
     * @param array $segments
     * @param int $position
     * @param int $duration
     * @return array
     */
    public static function execute(int $cmid, array $segments, int $position = 0, int $duration = 0): array {
        global $DB, $USER, $SESSION;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'segments' => $segments,
            'position' => $position,
            'duration' => $duration,
        ]);

        $cm = get_coursemodule_from_id('watchcomplete', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/watchcomplete:submit', $context);

        $wc = $DB->get_record('watchcomplete', ['id' => $cm->instance], '*', MUST_EXIST);

        if (count($params['segments']) > progress::MAX_SEGMENTS) {
            throw new \moodle_exception('err_toomanysegments', 'mod_watchcomplete');
        }

        // The duration is taken from the player only when the teacher has not set one, and is stored once.
        $duration = (int) $wc->duration;
        if ($duration === 0 && $params['duration'] > 0) {
            if ($params['duration'] > progress::MAX_DURATION) {
                throw new \moodle_exception('err_duration', 'mod_watchcomplete');
            }
            $duration = $params['duration'];
            $DB->set_field('watchcomplete', 'duration', $duration, ['id' => $wc->id]);
        }
        if ($duration <= 0) {
            throw new \moodle_exception('err_durationunknown', 'mod_watchcomplete');
        }

        $record = $DB->get_record('watchcomplete_progress', ['watchcompleteid' => $wc->id, 'userid' => $USER->id]);
        $existing = $record ? progress::parse($record->segments) : [];

        $incoming = [];
        foreach ($params['segments'] as $segment) {
            $incoming[] = [$segment['start'], $segment['end']];
        }
        $merged = progress::apply($existing, $incoming, $duration, (bool) $wc->preventskipping);
        if (count($merged) > progress::MAX_SEGMENTS) {
            throw new \moodle_exception('err_toomanysegments', 'mod_watchcomplete');
        }

        $summary = progress::summarise($merged, $duration, (int) $wc->threshold);

        // Refuse to credit more new video than could have been played since the last page view or save.
        $now = time();
        $baseline = max(
            $record ? (int) $record->timemodified : 0,
            (int) ($SESSION->mod_watchcomplete_start[$cm->id] ?? 0)
        );
        $elapsed = $baseline > 0 ? $now - $baseline : null;
        $gain = $summary['covered'] - ($record ? (int) $record->covered : 0);
        if ($gain > progress::max_new_seconds($elapsed)) {
            $kept = progress::summarise($existing, $duration, (int) $wc->threshold);
            return [
                'segments' => progress::encode($existing),
                'covered' => $kept['covered'],
                'percent' => $kept['percent'],
                'completed' => $record && (int) $record->completed === 1,
                'duration' => $duration,
            ];
        }

        $wascompleted = $record && (int) $record->completed === 1;
        $completed = $wascompleted || $summary['completed'];

        $row = (object) [
            'watchcompleteid' => $wc->id,
            'userid' => $USER->id,
            'segments' => progress::encode($merged),
            'covered' => $summary['covered'],
            'percent' => $summary['percent'],
            'lastposition' => max(0, min($params['position'], $duration)),
            'completed' => $completed ? 1 : 0,
            'timemodified' => $now,
        ];
        if ($record) {
            $row->id = $record->id;
            $row->timecreated = $record->timecreated;
            $DB->update_record('watchcomplete_progress', $row);
        } else {
            $row->timecreated = $now;
            $DB->insert_record('watchcomplete_progress', $row);
        }

        if ($completed && !$wascompleted) {
            $completion = new \completion_info($course);
            if ((int) $wc->completionwatched === 1 && $completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
            video_completed::create(['context' => $context, 'objectid' => $wc->id])->trigger();
        }

        return [
            'segments' => progress::encode($merged),
            'covered' => $summary['covered'],
            'percent' => $summary['percent'],
            'completed' => $completed,
            'duration' => $duration,
        ];
    }

    /**
     * Return definition.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'segments' => new \core_external\external_value(PARAM_TEXT, 'Merged segments, as start-end pairs'),
            'covered' => new \core_external\external_value(PARAM_INT, 'Unique seconds watched'),
            'percent' => new \core_external\external_value(PARAM_INT, 'Percentage of unique content watched'),
            'completed' => new \core_external\external_value(PARAM_BOOL, 'Whether the threshold has been met'),
            'duration' => new \core_external\external_value(PARAM_INT, 'Media length in seconds'),
        ]);
    }
}
