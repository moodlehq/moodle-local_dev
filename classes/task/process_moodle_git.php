<?php
// This file is part of Moodle - https://moodle.org/
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
 * Provides the {@link \local_dev\task\process_moodle_git} task.
 *
 * @package     local_dev
 * @category    task
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dev\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/dev/locallib.php');

/**
 * Record the recent activity in moodle.git and populate the dev activity database.
 *
 * @copyright 2018 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_moodle_git extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskprocess', 'local_dev');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        \local_dev\task\util::git_commits();
        \dev_git_aliases_manager::update_aliases();
        \local_dev\task\util::git_tags();
        \local_dev\task\util::sync_cohort();
    }
}
