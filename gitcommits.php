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
 * Displays the list of user's git commits
 *
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/local/dev/locallib.php');
require_once($CFG->dirroot.'/local/dev/tablelib.php');

//require_login(SITEID, false);

$version = required_param('version', PARAM_RAW);
$clean = preg_replace('/[^0-9\.]/', '', $version);
if ($version !== $clean) {
    print_error('missingparameter');
}
$userid = optional_param('userid', null, PARAM_INT);
if (empty($userid)) {
    $lastname = required_param('lastname', PARAM_RAW);
    $firstname = required_param('firstname', PARAM_RAW);
    $email = required_param('email', PARAM_RAW);
}
$merges = required_param('merges', PARAM_BOOL);

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/dev/gitcommits.php');
$PAGE->set_title(get_string('pluginname', 'local_dev'));
$PAGE->set_heading(get_string('pluginname', 'local_dev'));
navigation_node::override_active_url(new moodle_url('/local/dev/contributions.php'));

$output = $PAGE->get_renderer('local_dev');

$sql = "SELECT c.*,
               COALESCE(".$DB->sql_concat("u.firstname", "' '", "u.lastname").", c.authorname) AS author,
               COALESCE(u.email, c.authoremail) AS email
          FROM {dev_git_commits} c
     LEFT JOIN {user} u ON (c.userid = u.id)
         WHERE ".$DB->sql_like("c.tag", "?", false, false)." ";
$params = array('v'.$DB->sql_like_escape($version).'%');

if (!empty($userid)) {
    $sql .= " AND c.userid = ? ";
    $params[] = $userid;

} else {
    $sql .= " AND c.authorname = ? AND c.authoremail = ? ";
    $params[] = trim(implode(' ', array($firstname, $lastname)));
    $params[] = $email;
}

$sql .= " AND c.merge = ? ";
$params[] = $merges;

$sql .= " ORDER BY c.authordate DESC";

echo $output->header();
echo $output->heading(get_string('gitcommits', 'local_dev'));

$rs = $DB->get_recordset_sql($sql, $params);
foreach ($rs as $commit) {
    $commit->urlcommit = new moodle_url('https://github.com/moodle/moodle/commit/'.$commit->commithash);
    if ($commit->userid) {
        $commit->urlauthor = new moodle_url('/user/profile.php', array('id' => $commit->userid));
    } else {
        $commit->urlauthor = null;
    }
    echo $output->git_commit($commit);
}
$rs->close();

echo $output->footer();
