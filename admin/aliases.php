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
 * People directory management
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
$PAGE->set_url('/local/dev/admin/aliases.php');
$PAGE->set_title(get_string('aliases', 'local_dev'));
$PAGE->set_heading(get_string('aliases', 'local_dev'));

if ($action == 'new') {
    require_sesskey();
    $person = new stdClass();
    $person->firstname  = required_param('firstname', PARAM_NOTAGS);
    $person->lastname   = required_param('lastname', PARAM_NOTAGS);
    $person->email      = required_param('cleanemail', PARAM_EMAIL);
    $person->id         = $DB->insert_record('dev_persons', $person);

    $alias = new stdClass();
    $alias->fullname    = optional_param('fullname', null, PARAM_RAW);
    $alias->email       = optional_param('email', null, PARAM_RAW);

    if (!is_null($alias->fullname) and !is_null($alias->email)) {
        dev_persons_manager::add_alias($person->id, $alias->fullname, $alias->email);
        dev_persons_manager::update_aliases();
    }

    redirect($PAGE->url);
}

if ($action == 'map') {
    require_sesskey();
    $alias = new stdClass();
    $alias->personid    = required_param('personid', PARAM_INT);
    $alias->fullname    = required_param('fullname', PARAM_RAW);
    $alias->email       = required_param('email', PARAM_RAW);

    dev_persons_manager::add_alias($alias->personid, $alias->fullname, $alias->email);
    dev_persons_manager::update_aliases();

    redirect($PAGE->url);
}
$output = $PAGE->get_renderer('local_dev');

// get the list of unknown Git commit authors

$sql = "SELECT repository,authorname,authoremail,count(*) AS commits
          FROM {dev_git_commits}
         WHERE personid IS NULL
      GROUP BY repository,authorname,authoremail
      ORDER BY commits DESC";
$rs = $DB->get_recordset_sql($sql);

$persons = new dev_unknown_persons_list();

foreach ($rs as $r) {
    $persons->add_person($r->authorname, $r->authoremail, $r->repository, $r->commits);
}
$rs->close();

echo $output->header();
echo $output->render($persons);
echo $output->footer();
