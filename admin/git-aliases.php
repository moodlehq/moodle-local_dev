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
 * Displays the list of Git committers and lets the user to assign them to real user accounts
 *
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/local/dev/locallib.php');

require_login(SITEID, false);
require_capability('local/dev:manage', context_system::instance());

$action = optional_param('action', null, PARAM_ALPHA);

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/dev/admin/git-aliases.php');
$PAGE->set_title(get_string('gitaliases', 'local_dev'));
$PAGE->set_heading(get_string('gitaliases', 'local_dev'));
$PAGE->requires->yui_module('moodle-local_dev-gitaliases', 'M.local_dev.init_gitaliases');

if ($data = data_submitted()) {
    require_sesskey();
    $authorname = required_param('authorname', PARAM_RAW);
    $authoremail = required_param('authoremail', PARAM_RAW);
    $userid = required_param('userid', PARAM_INT);
    if (!empty($userid)) {
        $status = dev_git_aliases_manager::add_alias($userid, $authorname, $authoremail);
        if ($status === false) {
            print_error('gitaliasesconflict', 'local_dev');
        } else {
            dev_git_aliases_manager::update_aliases();
        }
    }
    redirect($PAGE->url);
}

$output = $PAGE->get_renderer('local_dev');

echo $output->header();

// get the list of unknown Git commit authors

$sql = "SELECT repository,authorname,authoremail,count(*) AS commits
          FROM {dev_git_commits}
         WHERE userid IS NULL
      GROUP BY repository,authorname,authoremail
      ORDER BY commits DESC";
$rs = $DB->get_recordset_sql($sql);

$table = new html_table();
$table->id = 'aliaseseditor';
$table->head = array(
    get_string('gitaliasesfullname', 'local_dev'),
    get_string('gitaliasesemail', 'local_dev'),
    get_string('gitaliasescommits', 'local_dev'),
    get_string('gitaliasesassign', 'local_dev'),
);

foreach ($rs as $record) {
    $table->data[] = array(
        html_writer::tag('div', s($record->authorname), array('class' => 'aliasdata-authorname')),
        html_writer::tag('div', s($record->authoremail), array('class' => 'aliasdata-authoremail')),
        s($record->commits.' '.$record->repository),
        html_writer::tag('form',
            html_writer::tag('div',
                html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'authorname', 'value' => $record->authorname)).
                html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'authoremail', 'value' => $record->authoremail)).
                html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey())).
                html_writer::empty_tag('input', array('type' => 'text', 'name' => 'search', 'class' => 'aliasdata-search', 'maxlength' => 100, 'size' => 50)).
                html_writer::empty_tag('input', array('type' => 'text', 'name' => 'userid', 'class' => 'aliasdata-userid', 'maxlength' => 100, 'size' => 5)).
                html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('submit'))).
                html_writer::empty_tag('input', array('type' => 'reset', 'value' => get_string('reset'))).
                html_writer::tag('span', ' ', array('class' => 'aliasdata-icon'))
            ),
        array('method' => 'post', 'action' => $PAGE->url->out()))
    );
}
echo html_writer::table($table);
$rs->close();

echo $output->footer();
