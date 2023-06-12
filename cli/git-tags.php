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
 * Records the most recent tag containing the commit in the database
 *
 * This basically calls `git describe --contains` on each commit in the database
 * and stores the result. Only the version release tags are supported (ie those
 * starting with the "v" character followed by a digit).
 *
 * The script can be also used to export and re-import populated data.
 *
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', 1);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(array(
    'show-progress' => false,
    'help' => false,
    'export' => false,
    'import' => '',
    'update-tags' => true,
), array('h' => 'help'));

if ($options['help']) {
    fputs(STDOUT, "Records the most recent tag containing the commit in the database

--show-progress         Display the progress indicator
--export                Dump the currently registered data
--import=file           Load data from a file
--update-tags           Tries to find the relevant tags for untagged commits
--help                  Display this help

");
    exit(0);
}

if ($options['export']) {
    $sql = "SELECT commithash, tag
              FROM {dev_git_commits}
             WHERE tag IS NOT NULL";
    $tags = $DB->get_recordset_sql($sql);
    foreach ($tags as $tag) {
        fputs(STDOUT, $tag->commithash.' '.$tag->tag."\n");
    }
    $tags->close();
    exit(0);
}

if ($filename = $options['import']) {
    if (!is_readable($filename)) {
        cli_error('File not readable');
    }
    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $total = count($lines);
    $counter = 0;
    foreach ($lines as $line) {
        $commithash = substr($line, 0, 40);
        $tag = substr($line, 41);
        $DB->set_field('dev_git_commits', 'tag', $tag, array('repository' => 'moodle.git', 'commithash' => $commithash));
        if ($options['show-progress']) {
            fputs(STDOUT, ++$counter.'/'.$total."\r");
        }
    }
    exit(0);
}

if ($options['update-tags']) {
    \local_dev\task\util::git_tags($options);
}
