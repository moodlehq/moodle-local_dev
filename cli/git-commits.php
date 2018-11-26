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
 * Registers git commits and their branches in the database
 *
 * @package     local_dev
 * @copyright   2011 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require('../../../config.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(array(
    'help' => false,
    'show-progress' => false,
    'reset-startpoints' => false,
), array('h' => 'help'));

if ($options['help']) {
    fputs(STDOUT, "Registers git commits and their branches in the database

--show-progress         Display the progress indicator
--reset-startpoints     Re-start from the first commit in the repository
--help                  Display this help

");
    exit(0);
}

fputs(STDOUT, "*****************************************\n");
fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " GIT-COMMITS JOB STARTED\n");

\local_dev\task\util::git_commits($options);

fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " GIT-COMMITS JOB DONE\n");
exit(0);
