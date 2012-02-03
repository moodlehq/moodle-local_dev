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
 * Assigns registered git commits to the real users
 *
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', 1);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/dev/locallib.php');

list($options, $unrecognized) = cli_get_params(array(
    'help' => false,
    'import' => '',
), array('h' => 'help'));

if ($options['help']) {
    fputs(STDOUT, "Assigns registered git commits to the real users

--import=file           Loads legacy CVS data from a file
--help                  Display this help

");
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
        $data = explode("\t", $line);
        $userid = trim($data[0]);
        $cvsname = trim($data[1]);
        if (!is_numeric($userid)) {
            continue;
        }
        dev_git_aliases_manager::add_alias($userid, $cvsname, $cvsname);
    }
    dev_git_aliases_manager::update_aliases();
    exit(0);
}

dev_git_aliases_manager::update_aliases();
