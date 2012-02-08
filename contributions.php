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

$version = optional_param('version', null, PARAM_RAW);
if (!is_null($version)) {
    $clean = preg_replace('/[^x0-9\.]/', '', $version);
    if ($version !== $clean) {
        $version = null;
    }
}

//require_login(SITEID, false);

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/dev/contributions.php');
$PAGE->set_title(get_string('pluginname', 'local_dev'));
$PAGE->set_heading(get_string('pluginname', 'local_dev'));

$output = $PAGE->get_renderer('local_dev');

// prepare the list of known versions

$knownversions = $DB->get_records("dev_activity", array(), '', "DISTINCT version");
$versions = array();
foreach (array_keys($knownversions) as $knownversion) {
    $bits = explode('.', $knownversion, 3);
    $versions[$bits[0]*1e9 + $bits[1]*1e6 + 1e3] = $bits[0].'.'.$bits[1].'.x';
    $versions[$bits[0]*1e9 + $bits[1]*1e6 + $bits[2]] = $knownversion;
}
unset($knownversions);
krsort($versions);
$versions = array_flip($versions);
foreach (array_keys($versions) as $v) {
    $versions[$v] = $v;
}

if (!isset($versions[$version])) {
    $keys = array_keys($versions);
    $version = $versions[$keys[1]];
}

echo $output->header();
echo $output->box($output->single_select($PAGE->url, 'version', $versions, $version), array('generalbox versionselector'));

if (is_null($version)) {
    echo $output->footer();
    die();
}

// populate list of versions to display contributions for
$bits = explode('.', $version, 3);
$display = array();
if ($bits[2] === 'x') {
    foreach ($versions as $v) {
        if (substr($v, -1) === 'x') {
            continue;
        }
        if (strpos($v, $bits[0].'.'.$bits[1].'.') === 0) {
            $display[] = $v;
        }
    }
} else {
    $display[] = $version;
}

echo $output->heading(get_string('contributionsheading', 'local_dev', $version));

$metrics = array('gitcommits', 'gitmerges');
$table = new dev_activity_table_sql('dev-activity-table');
$sqlfields = "a.id, a.version,
    COALESCE(u.firstname, a.userfirstname) AS firstname,
    COALESCE(u.lastname, a.userlastname) AS lastname,
    COALESCE(u.email, a.useremail) AS email,".
    user_picture::fields("u", array("country", "institution"), "realuserid", "realuser").",".
    implode(",", $metrics);
$sqlfrom = "{dev_activity} a LEFT JOIN {user} u ON (a.userid = u.id)";
$sqlwheremetrics = array();
foreach ($metrics as $metric) {
    $sqlwheremetrics[] = "$metric IS NOT NULL";
}
$sqlwhere  = "(" . implode(" OR ", $sqlwheremetrics) . ")";
list($subsql, $sqlparams) = $DB->get_in_or_equal($display);
$sqlwhere .= " AND version $subsql";
$table->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);
$table->set_count_sql("SELECT COUNT(*) FROM {dev_activity} a WHERE $sqlwhere", $sqlparams);

$columns = array();
$headers = array();
if (!$table->is_downloading()) {
    $columns[] = 'userpic';
    $headers[] = '';
    $columns[] = 'fullname';
    $headers[] = get_string('name');
} else {
    $columns[] = 'lastname';
    $headers[] = get_string('lastname');
    $columns[] = 'firstname';
    $headers[] = get_string('firstname');
}

$columns[] = 'realusercountry';
$headers[] = get_string('country');

$columns[] = 'realuserinstitution';
$headers[] = get_string('institution');

$columns[] = 'version';
$headers[] = get_string('version');

$columns[] = 'gitcommits';
$headers[] = get_string('gitcommits', 'local_dev');

$columns[] = 'gitmerges';
$headers[] = get_string('gitmerges', 'local_dev');

$table->define_columns($columns);
$table->define_headers($headers);
$table->column_suppress('userpic');
$table->column_suppress('fullname');
$table->sortable(true, 'commits', SORT_DESC);
$table->define_baseurl(new moodle_url($PAGE->url, array('version' => $version)));
$table->out(100, true, true);

echo $output->footer();
