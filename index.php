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
 * Displays the cloud of developer names
 *
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/local/dev/lib.php');
require_once($CFG->dirroot.'/local/dev/locallib.php');
require_once($CFG->dirroot.'/local/dev/tablelib.php');

//require_login(SITEID, false);

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_url(new local_dev_url('/local/dev/index.php'));
$PAGE->add_body_class('path-local-dev');
$PAGE->set_title(get_string('pluginname', 'local_dev'));
$PAGE->set_heading(get_string('pluginname', 'local_dev'));

$output = $PAGE->get_renderer('local_dev');

$devs = array();
$cache = cache::make('local_dev', 'apuwasadev');
$devs = $cache->get('devs');
$max = $cache->get('devsmaxcommits');
$gentime = $cache->get('devsgentime');

echo $output->header();
echo $output->heading(get_string('developers', 'local_dev'));
if ($devs) {
    shuffle($devs);
    echo $output->box(get_string('developersinfo', 'local_dev', 'http://moodle.org/dev/contributions.php'));
    echo $output->box_start(array('devscloud'));
    foreach ($devs as $dev) {
        if ($dev->commits <= 1 or $max == 0) {
            $rel = 0;
        } else {
            $rel = round(log($dev->commits) / log($max) * 100);
        }
        $rel = 3 * max($rel, 25);
        echo html_writer::tag('span', ' '.$dev->name.' ', array('style' => sprintf('font-size:%d%%', $rel)));
    }
    echo $output->box_end();
} else {
    echo $output->box(get_string('developersinfo', 'local_dev', 'http://moodle.org/dev/contributions.php')); //just show link to contributions page
}

if ($gentime == false || ($gentime !== true and $gentime + 30 < time())) {
    error_log('debug(MDLSITE-3080):regenerating local/dev/index.php cache. cache status: $devs '. gettype($devs). ' , $max '. gettype($max). ', $gentime '. $gentime );
    $cache->set('devsgentime', true); // set gentime to skip more requests triggering sql.
    $max = 1;
    $sql = "SELECT c.userid,
                   COALESCE(".$DB->sql_concat("u.firstname", "' '", "u.lastname").", c.authorname) AS xname,
                   COALESCE(u.email, c.authorname) AS xemail,
                   COUNT(c.commithash) AS xcommits
              FROM {dev_git_commits} c
         LEFT JOIN {user} u ON (c.userid = u.id)
          GROUP BY userid, xname, xemail";

    $rs = $DB->get_recordset_sql($sql, array('%.x'));
    foreach ($rs as $record) {
        $dev = new stdClass();
        $fullname = s(trim($record->xname));
        $fullname = html_writer::tag('span', $fullname, array('class' => 'cx' . $record->xcommits));
        if (is_null($record->userid)) {
            $dev->name = $fullname;
        } else {
            $dev->name = html_writer::link(new moodle_url('/user/profile.php', array('id' => $record->userid)), $fullname);
        }
        $dev->commits = $record->xcommits;
        $max = $dev->commits > $max ? $dev->commits : $max;
        $devs[] = $dev;
    }
    $rs->close();
    $cache->set('devs', $devs);
    $cache->set('devsmaxcommits', $max);
    $cache->set('devsgentime', time()); //set proper gen time.
}
echo $output->footer();