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
 * Privacy API tests for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_watchcomplete\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\tests\provider_testcase;

/**
 * Tests that progress is reported, exported and deleted as the Privacy API requires.
 *
 * @group mod_watchcomplete
 * @covers \mod_watchcomplete\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * Create an activity with progress for one student and return the pieces the tests need.
     *
     * @return array [student, context, other student, other context]
     */
    private function create_data(): array {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student');

        $wc = $this->getDataGenerator()->create_module('watchcomplete', [
            'course' => $course->id,
            'name' => 'Privacy test',
            'videosource' => 'youtube',
            'videourl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'threshold' => 90,
        ]);
        $context = \context_module::instance($wc->cmid);

        foreach ([$student, $other] as $user) {
            $DB->insert_record('watchcomplete_progress', (object) [
                'watchcompleteid' => $wc->id,
                'userid' => $user->id,
                'segments' => '0-60',
                'covered' => 60,
                'percent' => 40,
                'lastposition' => 60,
                'completed' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        return [$student, $context, $other];
    }

    /**
     * The metadata declares the progress table and the external services.
     */
    public function test_metadata_declares_storage_and_external_services(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_watchcomplete'));
        $items = $collection->get_collection();
        $this->assertCount(3, $items);
    }

    /**
     * A student's contexts list the activity they have progress in.
     */
    public function test_get_contexts_for_user(): void {
        [$student, $context] = $this->create_data();
        $contextlist = provider::get_contexts_for_userid($student->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context->id, $contextlist->get_contextids()[0]);
    }

    /**
     * Deleting a user's data removes only that user's progress.
     */
    public function test_delete_data_for_user_keeps_other_students(): void {
        global $DB;

        [$student, $context, $other] = $this->create_data();
        $approved = new approved_contextlist($student, 'mod_watchcomplete', [$context->id]);
        provider::delete_data_for_user($approved);

        $this->assertFalse($DB->record_exists('watchcomplete_progress', ['userid' => $student->id]));
        $this->assertTrue($DB->record_exists('watchcomplete_progress', ['userid' => $other->id]));
    }

    /**
     * Deleting everything in a context removes all progress for the activity.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        [, $context] = $this->create_data();
        provider::delete_data_for_all_users_in_context($context);
        $this->assertEquals(0, $DB->count_records('watchcomplete_progress'));
    }
}
