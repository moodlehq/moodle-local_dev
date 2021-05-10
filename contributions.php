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
require_once($CFG->dirroot.'/local/dev/lib.php');
require_once($CFG->dirroot.'/local/dev/locallib.php');
require_once($CFG->dirroot.'/local/dev/tablelib.php');

$version = optional_param('version', null, PARAM_RAW);
if (empty($version)) {
    $version = null;
}
if (!is_null($version)) {
    if ($version !== 'x.x.x') {
        $version = preg_replace('/[^x0-9\.]/', '', $version);
    }
}

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_url(new local_dev_url('/local/dev/contributions.php', array('version' => $version)));
$PAGE->add_body_class('path-local-dev');
$PAGE->set_title(get_string('pluginname', 'local_dev'));
$PAGE->set_heading(get_string('pluginname', 'local_dev'));

$output = $PAGE->get_renderer('local_dev');

// prepare the drop down box with versions
$options = array('x.x.x' => get_string('allversions', 'local_dev'));
$validversion = is_null($version);
$branches = dev_aggregator::get_branches();
foreach ($branches as $branch => $vers) {
    if ($version === 'x.x.x') {
        $validversion = true;
    } else if ($version === $branch) {
        $validversion = true;
    }
    $optgroup = array($branch => get_string('allversionsonbranch', 'local_dev', $branch));
    foreach ($vers as $ver) {
        $optgroup[$ver] = $ver;
        if ($version === $ver) {
            $validversion = true;
        }
    }
    $options[] = array(('Moodle '.$branch) => $optgroup);
}

if (empty($CFG->hidelocaldevfromnavigation)) {
    $rootnode = $PAGE->navigation->find('local_dev-contributions', navigation_node::TYPE_CUSTOM);
    foreach ($options as $key => $val) {
        if (is_array($val)) {
            $vers = array_keys(reset($val));
            $branch = array_shift($vers);
            $branchnode = $rootnode->add($branch, new local_dev_url('/local/dev/contributions.php', array('version' => $branch)));
            foreach ($vers as $ver) {
                $branchnode->add($ver, new local_dev_url('/local/dev/contributions.php', array('version' => $ver)));
            }
        }
    }
}

echo $output->header();

if (empty($branches)) {
    echo $output->box('Unable to find any branch, are you sure you have executed cli/aggregate.php?');
    echo $output->footer();
    die();
}

if (!$validversion) {
    // the version has the correct format but is not known, for example 1.8.99
    echo $output->heading(get_string('contributionsversioninvalid', 'local_dev', $version));
} else {
    if (is_null($version)) {
        // version not specified or has invalid format - use 'All versions'
        $version = 'x.x.x';
    }
    if ($version === 'x.x.x') {
        echo $output->heading(get_string('contributionsversionall', 'local_dev'));
    } else {
        echo $output->heading(get_string('contributionsversion', 'local_dev', $version));
    }
}

$select = new single_select(new local_dev_url('/local/dev/contributions.php'), 'version', $options, $version);
$select->set_label(get_string('contributionsversionselect', 'local_dev'));

if (!$validversion) {
    echo $output->box($output->render($select), array('generalbox versionselector'));
    echo $output->footer();
    die();
}

$metrics = array('gitcommits', 'gitmerges');
$table = new dev_activity_table_sql('dev-activity-table');
$sqlfields = "a.id, a.version,
    COALESCE(u.firstname, a.userfirstname) AS firstname,
    COALESCE(u.lastname, a.userlastname) AS lastname,
    COALESCE(u.email, a.useremail) AS email" .
    \core_user\fields::for_userpic()->including('country', 'institution')->get_sql('u', false, 'realuser', 'realuserid')->selects .
    ", " . implode(",", $metrics);
$sqlfrom = "{dev_activity} a LEFT JOIN {user} u ON (a.userid = u.id)";
$sqlwheremetrics = array();
foreach ($metrics as $metric) {
    $sqlwheremetrics[] = "$metric IS NOT NULL";
}
$sqlwhere  = "(" . implode(" OR ", $sqlwheremetrics) . ")";
$sqlwhere .= " AND version = :version";
$sqlparams = array('version' => $version);
$table->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);

$statsql = "SELECT COUNT(*) AS devs, COUNT(DISTINCT r.realusercountry) AS countries, SUM(r.gitcommits) AS commits
              FROM (SELECT $sqlfields
                      FROM $sqlfrom
                     WHERE $sqlwhere) r";

$stats = $DB->get_record_sql($statsql, $sqlparams);

echo $output->container_start('stats');
echo $output->container(get_string('statsdevs', 'local_dev', html_writer::span($stats->devs)), 'devs');
echo $output->container(get_string('statscountries', 'local_dev', html_writer::span($stats->countries)), 'countries');
echo $output->container(get_string('statscommits', 'local_dev', html_writer::span($stats->commits)), 'commits');
echo $output->container_end();

echo $output->box($output->render($select), array('generalbox versionselector'));

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

//$columns[] = 'realuserinstitution'; // removed, see MDLSITE-3080
//$headers[] = get_string('institution');

$columns[] = 'gitcommits';
$headers[] = get_string('gitcommits', 'local_dev');

$columns[] = 'gitmerges';
$headers[] = get_string('gitmerges', 'local_dev');

$table->define_columns($columns);
$table->define_headers($headers);
$table->sortable(true, 'gitcommits', SORT_DESC);
$table->define_baseurl($PAGE->url);
$table->initialbars(true);
$table->out(100, true, true);

echo $output->footer();
