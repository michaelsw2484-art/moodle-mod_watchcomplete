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
 * CSV export of student progress.
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

$columns = [
    'fullname' => get_string('colstudent', 'mod_watchcomplete'),
    'percent' => get_string('colwatched', 'mod_watchcomplete'),
    'status' => get_string('colstatus', 'mod_watchcomplete'),
    'lastupdate' => get_string('collastupdate', 'mod_watchcomplete'),
];

$data = [];
foreach (report::rows($wc, $context) as $row) {
    $data[] = [
        'fullname' => $row->fullname,
        'percent' => $row->percent,
        'status' => report::status_label($row->status),
        'lastupdate' => $row->timemodified ? userdate($row->timemodified) : get_string('neverupdated', 'mod_watchcomplete'),
    ];
}

\core\dataformat::download_data(clean_filename($wc->name) . '_progress', 'csv', $columns, $data);
