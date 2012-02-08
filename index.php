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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/local/dev/locallib.php');
require_once($CFG->dirroot.'/local/dev/tablelib.php');

//require_login(SITEID, false);

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/dev/index.php');
$PAGE->set_title(get_string('pluginname', 'local_dev'));
$PAGE->set_heading(get_string('pluginname', 'local_dev'));

$output = $PAGE->get_renderer('local_dev');

$sql = "SELECT DISTINCT a.userid, COALESCE(u.firstname, a.userfirstname) AS firstname,
               COALESCE(u.lastname, a.userlastname) AS lastname
          FROM {dev_activity} a
     LEFT JOIN {user} u ON (a.userid = u.id)
         WHERE a.gitcommits IS NOT NULL
      ORDER BY lastname, firstname";

$rs = $DB->get_recordset_sql($sql);
$names = array();
foreach ($rs as $record) {
    if ($record->firstname === 'Moodle HQ git') {
        continue;
    }
    if (is_null($record->userid)) {
        $names[] = fullname($record);
    } else {
        $names[] = html_writer::link(new moodle_url('/user/profile.php', array('id' => $record->userid)), fullname($record));
    }
}
$rs->close();

echo $output->header();
echo $output->heading(get_string('developers', 'local_dev'));
echo $output->box(
    html_writer::tag('p', get_string('developersinfo', 'local_dev')).
    html_writer::tag('div', implode(', ', $names))
);
echo $output->footer();
