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
 * Builds the per-student progress rows shared by the report and the CSV export.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_watchcomplete\local;

/**
 * Report helper.
 */
final class report {
    /**
     * Get one row per enrolled student who can submit progress.
     *
     * @param \stdClass $wc The watchcomplete instance record.
     * @param \context_module $context
     * @return \stdClass[] Rows with userid, fullname, percent, status and timemodified.
     */
    public static function rows(\stdClass $wc, \context_module $context): array {
        global $DB;

        $students = get_enrolled_users(
            $context,
            'mod/watchcomplete:submit',
            0,
            'u.*',
            'u.lastname, u.firstname',
            0,
            0,
            true
        );

        $byuser = [];
        foreach ($DB->get_records('watchcomplete_progress', ['watchcompleteid' => $wc->id]) as $p) {
            $byuser[$p->userid] = $p;
        }

        $rows = [];
        foreach ($students as $student) {
            $p = $byuser[$student->id] ?? null;
            if ($p === null) {
                $status = 'notstarted';
                $percent = 0;
                $updated = 0;
            } else {
                $status = $p->completed ? 'completed' : 'inprogress';
                $percent = (int) $p->percent;
                $updated = (int) $p->timemodified;
            }
            $rows[] = (object) [
                'userid' => (int) $student->id,
                'fullname' => fullname($student),
                'percent' => $percent,
                'status' => $status,
                'timemodified' => $updated,
            ];
        }
        return $rows;
    }

    /**
     * Human-readable status label.
     *
     * @param string $status notstarted, inprogress or completed.
     * @return string
     */
    public static function status_label(string $status): string {
        return get_string('status_' . $status, 'mod_watchcomplete');
    }
}
