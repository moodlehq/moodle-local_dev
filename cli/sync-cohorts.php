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
 * Populates cohorts from local_dev activity
 *
 * @package     local_dev
 * @copyright   2012 Dan Poltawski <dan@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', 1);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/cohort/lib.php');

$sql = "SELECT DISTINCT userid
        FROM {dev_git_commits}
        WHERE userid IS NOT NULL
        ORDER BY userid";
$rs = $DB->get_recordset_sql($sql);

$cohortmanager = new dev_cohort_manager('Developers');
foreach($rs as $record) {
    $cohortmanager->add_member($record->userid);
}

$rs->close();

/**
 * Manages cohort creation from dev plugin. Using this manager will create a cohort
 * for the identifer.
 *
 * Used to automatically populate cohorts from dev plugin data.
 */
class dev_cohort_manager {
    /** @var object cohort object from cohort table */
    private $cohort;
    /** @var array of cohort members indexed by userid */
    private $members;


    /**
     * Creates a cohort for identifier if it doesn't exist
     *
     * @param string $identifier identifier of cohort uniquely identifiying cohorts between dev plugin generated cohorts
     */
    public function __construct($identifier) {
        global $DB;

        $cohort = new stdClass;
        $cohort->idnumber = 'local_dev:'.$identifier;
        $cohort->component = 'local_dev';

        if ($existingcohort = $DB->get_record('cohort', (array) $cohort)) {
            $this->cohort = $existingcohort;
            // populate cohort members array based on existing members
            $this->members = $DB->get_records('cohort_members', array('cohortid' => $this->cohort->id), 'userid', 'userid');
        } else {
            $cohort->contextid = context_system::instance()->id;
            $cohort->name = $identifier;
            $cohort->description = 'Automatically generated cohort from developer plugin for ['.$identifier.']';
            $cohort->id = cohort_add_cohort($cohort);

            $this->cohort = $cohort;
            // no existing members as we've just created cohort
            $this->members = array();
        }
    }

    /**
     * Add a member to the cohort if they are not alread in the cohort
     *
     * @param int $userid id from user table of user
     * @return void
     */
    public function add_member($userid) {
        if (!isset($this->members[$userid])) {
            cohort_add_member($this->cohort->id, $userid);
            $this->members[$userid] = $userid;
        }
    }
}
