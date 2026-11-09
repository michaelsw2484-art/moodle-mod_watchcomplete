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
 * Privacy API provider for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_watchcomplete\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use core_privacy\local\request\plugin\provider as plugin_provider;

/**
 * Stores and exports per-student watch progress.
 */
class provider implements \core_privacy\local\metadata\provider, core_userlist_provider, plugin_provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('watchcomplete_progress', [
            'userid' => 'privacy:metadata:watchcomplete_progress:userid',
            'segments' => 'privacy:metadata:watchcomplete_progress:segments',
            'covered' => 'privacy:metadata:watchcomplete_progress:covered',
            'percent' => 'privacy:metadata:watchcomplete_progress:percent',
            'lastposition' => 'privacy:metadata:watchcomplete_progress:lastposition',
            'completed' => 'privacy:metadata:watchcomplete_progress:completed',
            'timecreated' => 'privacy:metadata:watchcomplete_progress:timecreated',
            'timemodified' => 'privacy:metadata:watchcomplete_progress:timemodified',
        ], 'privacy:metadata:watchcomplete_progress');

        // The player loads scripts and video from these services, so the browser sends them the viewer's
        // address and browser details when the video starts.
        $fields = [
            'useragent' => 'privacy:metadata:player:useragent',
            'ipaddress' => 'privacy:metadata:player:ipaddress',
        ];
        $collection->add_external_location_link('youtube', $fields, 'privacy:metadata:youtube');
        $collection->add_external_location_link('vimeo', $fields, 'privacy:metadata:vimeo');
        return $collection;
    }

    /**
     * Contexts that hold data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {watchcomplete} wc ON wc.id = cm.instance
                  JOIN {watchcomplete_progress} p ON p.watchcompleteid = wc.id
                 WHERE p.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'watchcomplete',
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT p.userid
                  FROM {course_modules} cm
                  JOIN {watchcomplete} wc ON wc.id = cm.instance
                  JOIN {watchcomplete_progress} p ON p.watchcompleteid = wc.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Export a user's progress.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!count($contextlist)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $wc = self::instance_for_context($context);
            $progress = $DB->get_record('watchcomplete_progress', ['watchcompleteid' => $wc->id, 'userid' => $userid]);
            if (!$progress) {
                continue;
            }
            writer::with_context($context)->export_data([get_string('pluginname', 'mod_watchcomplete')], (object) [
                'segments' => $progress->segments,
                'covered' => $progress->covered,
                'percent' => $progress->percent,
                'lastposition' => $progress->lastposition,
                'completed' => transform::yesno($progress->completed),
                'timecreated' => transform::datetime($progress->timecreated),
                'timemodified' => transform::datetime($progress->timemodified),
            ]);
        }
    }

    /**
     * Delete all progress in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $wc = self::instance_for_context($context);
        $DB->delete_records('watchcomplete_progress', ['watchcompleteid' => $wc->id]);
    }

    /**
     * Delete a user's progress in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $wc = self::instance_for_context($context);
            $DB->delete_records('watchcomplete_progress', ['watchcompleteid' => $wc->id, 'userid' => $userid]);
        }
    }

    /**
     * Delete progress for approved users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $wc = self::instance_for_context($context);
        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            'watchcomplete_progress',
            "watchcompleteid = :wcid AND userid $insql",
            ['wcid' => $wc->id] + $inparams
        );
    }

    /**
     * Look up the activity instance for a module context.
     *
     * @param \context $context
     * @return \stdClass
     */
    private static function instance_for_context(\context $context): \stdClass {
        global $DB;

        $cm = get_coursemodule_from_id('watchcomplete', $context->instanceid, 0, false, MUST_EXIST);
        return $DB->get_record('watchcomplete', ['id' => $cm->instance], '*', MUST_EXIST);
    }
}
