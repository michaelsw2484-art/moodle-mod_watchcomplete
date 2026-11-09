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
 * Teacher report of student progress.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_watchcomplete\local\report;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'watchcomplete');
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/watchcomplete:viewreports', $context);
$wc = $DB->get_record('watchcomplete', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/watchcomplete/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('reportheading', 'mod_watchcomplete'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);

$rows = report::rows($wc, $context);
$counts = ['completed' => 0, 'inprogress' => 0, 'notstarted' => 0];
foreach ($rows as $row) {
    $counts[$row->status]++;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reportheading', 'mod_watchcomplete') . ': ' . format_string($wc->name));
echo html_writer::tag('p', get_string('reportsummary', 'mod_watchcomplete', (object) $counts));

echo $OUTPUT->single_button(
    new moodle_url('/mod/watchcomplete/export.php', ['id' => $cm->id]),
    get_string('exportcsv', 'mod_watchcomplete')
);

if (!$rows) {
    echo $OUTPUT->notification(get_string('noparticipants', 'mod_watchcomplete'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('colstudent', 'mod_watchcomplete'),
        get_string('colwatched', 'mod_watchcomplete'),
        get_string('colstatus', 'mod_watchcomplete'),
        get_string('collastupdate', 'mod_watchcomplete'),
    ];
    $table->attributes['class'] = 'generaltable watchcomplete-report';
    $table->caption = get_string('reportheading', 'mod_watchcomplete') . ': ' . format_string($wc->name);
    $table->captionhide = false;
    foreach ($rows as $row) {
        $table->data[] = [
            s($row->fullname),
            $row->percent . '%',
            report::status_label($row->status),
            $row->timemodified ? userdate($row->timemodified) : get_string('neverupdated', 'mod_watchcomplete'),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
