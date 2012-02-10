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
if (empty($version)) {
    $version = null;
}
if (!is_null($version)) {
    $clean = preg_replace('/[^x0-9\.]/', '', $version);
}

//require_login(SITEID, false);

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_url(new local_dev_url('/local/dev/contributions.php'));
$PAGE->add_body_class('path-local-dev');
$PAGE->set_title(get_string('pluginname', 'local_dev'));
$PAGE->set_heading(get_string('pluginname', 'local_dev'));

$output = $PAGE->get_renderer('local_dev');

// prepare the drop down box with versions
$options = array();
$validversion = is_null($version);
$branches = dev_aggregator::get_branches();
foreach ($branches as $branch => $vers) {
    if ($version === $branch) {
        $validversion = true;
    }
    $optgroup = array($branch => get_string('allversions', 'local_dev', $branch));
    foreach ($vers as $ver) {
        $optgroup[$ver] = $ver;
        if ($version === $ver) {
            $validversion = true;
        }
    }
    $options[] = array(('Moodle '.$branch) => $optgroup);
}

echo $output->header();

if (!$validversion) {
    // the version has the correct format but is not known, for example 1.8.99
    echo $output->heading(get_string('contributionsversioninvalid', 'local_dev', $version));
} else {
    if (is_null($version)) {
        // version not specified or has invalid format - use the most recent version by the default
        $version = reset(reset($branches));
    }
    echo $output->heading(get_string('contributionsversion', 'local_dev', $version));
}

$select = new single_select($PAGE->url, 'version', $options, $version);
$select->set_label(get_string('contributionsversionselect', 'local_dev'));

echo $output->box($output->render($select), array('generalbox versionselector'));

if (!$validversion) {
    echo $output->footer();
    die();
}

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
$sqlwhere .= " AND version = ?";
$sqlparams = array($version);
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

$columns[] = 'gitcommits';
$headers[] = get_string('gitcommits', 'local_dev');

$columns[] = 'gitmerges';
$headers[] = get_string('gitmerges', 'local_dev');

$table->define_columns($columns);
$table->define_headers($headers);
$table->sortable(true, 'commits', SORT_DESC);
$table->define_baseurl(new moodle_url($PAGE->url, array('version' => $version)));
$table->out(100, true, true);

echo $output->footer();
