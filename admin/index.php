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

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/local/dev/lib.php');
require_once($CFG->dirroot.'/local/dev/locallib.php');

require_login(SITEID, false);
require_capability('local/dev:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_url(new local_dev_url('/local/dev/admin/index.php'));
$PAGE->add_body_class('path-local-dev');
$PAGE->set_title(get_string('pluginname', 'local_dev'));
$PAGE->set_heading(get_string('pluginname', 'local_dev'));

$output = $PAGE->get_renderer('local_dev');

$info['git commits']['total'] = $DB->count_records('dev_git_commits');
$info['git commits']['unassigned'] = $DB->count_records_select('dev_git_commits', 'userid IS NULL');

$startpoints = get_config('local_dev', 'gitstartpoints');
if ($startpoints === false) {
    $info['git startpoints']['N/A'] = 'N/A';
} else {
    $startpoints = json_decode($startpoints, true);
    foreach ($startpoints as $branch => $modes) {
        foreach ($modes as $mode => $hash) {
            $info['git startpoints'][$branch.' ('.$mode.')'] = $hash;
        }
    }
}

echo $output->header();
foreach ($info as $section => $subsections) {
    $table = new html_table();
    $table->attributes['style'] = 'float:left; margin:1em;';
    $table->head = array($section);
    $table->headspan = array(2);
    foreach ($subsections as $name => $value) {
        $table->data[] = array($name, $value);
    }
    echo html_writer::table($table);
}
echo $output->footer();
