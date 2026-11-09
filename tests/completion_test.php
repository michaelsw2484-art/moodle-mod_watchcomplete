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
 * Tests for the "Watch to threshold" completion rule.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_watchcomplete\completion;

/**
 * Tests that the rule reports completion only once the threshold has been reached.
 *
 * @group mod_watchcomplete
 * @covers \mod_watchcomplete\completion\custom_completion
 */
final class completion_test extends \advanced_testcase {
    /**
     * Create a course, an activity with the watch rule, and a student.
     *
     * @return array [cm_info, student user record]
     */
    private function create_activity(): array {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $wc = $this->getDataGenerator()->create_module('watchcomplete', [
            'course' => $course->id,
            'name' => 'Watch test',
            'videosource' => 'youtube',
            'videourl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'threshold' => 90,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionwatched' => 1,
        ]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($wc->cmid);
        return [$cm, $student];
    }

    /**
     * Save a progress row for a student.
     *
     * @param \cm_info $cm
     * @param int $userid
     * @param int $completed
     */
    private function save_progress(\cm_info $cm, int $userid, int $completed): void {
        global $DB;

        $DB->insert_record('watchcomplete_progress', (object) [
            'watchcompleteid' => $cm->instance,
            'userid' => $userid,
            'segments' => '0-100',
            'covered' => 100,
            'percent' => $completed ? 90 : 40,
            'lastposition' => 100,
            'completed' => $completed,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * A student with no progress is incomplete, not complete.
     */
    public function test_no_progress_is_incomplete(): void {
        [$cm, $student] = $this->create_activity();
        $completion = new custom_completion($cm, $student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state(custom_completion::RULE_WATCHED));
    }

    /**
     * Partial progress stays incomplete.
     */
    public function test_partial_progress_is_incomplete(): void {
        [$cm, $student] = $this->create_activity();
        $this->save_progress($cm, $student->id, 0);
        $completion = new custom_completion($cm, $student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state(custom_completion::RULE_WATCHED));
    }

    /**
     * Reaching the threshold marks the rule complete.
     */
    public function test_threshold_reached_is_complete(): void {
        [$cm, $student] = $this->create_activity();
        $this->save_progress($cm, $student->id, 1);
        $completion = new custom_completion($cm, $student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state(custom_completion::RULE_WATCHED));
    }

    /**
     * Unknown rules are reported as unknown rather than complete.
     */
    public function test_unknown_rule_is_unknown(): void {
        [$cm, $student] = $this->create_activity();
        $completion = new custom_completion($cm, $student->id);
        $this->assertEquals(COMPLETION_UNKNOWN, $completion->get_state('completionunknown'));
    }

    /**
     * The description includes the threshold from the activity.
     */
    public function test_description_includes_threshold(): void {
        [$cm, $student] = $this->create_activity();
        $completion = new custom_completion($cm, $student->id);
        $descriptions = $completion->get_custom_rule_descriptions();
        $this->assertStringContainsString('90', $descriptions[custom_completion::RULE_WATCHED]);
    }
}
