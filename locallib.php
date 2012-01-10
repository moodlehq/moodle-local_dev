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
 * The plugin's internal API
 *
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Persons (developers, integrators, reporters etc) management
 */
class dev_persons_manager {

    /**
     * Registers new alias for the given person
     *
     * @param int $personid
     * @param string $fullname
     * @param string $email
     */
    public static function add_alias($personid, $fullname, $email) {
        global $DB;

        if (is_null($personid) or is_null($fullname) or is_null($email)) {
            throw new coding_exception('NULL parameter values not allowed here');
        }

        $existing = $DB->get_record('dev_person_aliases', array('fullname' => $fullname, 'email' => $email), 'personid', IGNORE_MISSING);

        if ($existing === false) {
            $alias = new stdClass();
            $alias->personid = $personid;
            $alias->fullname = $fullname;
            $alias->email    = $email;
            $DB->insert_record('dev_person_aliases', $alias);

        } else if ($existing->personid != $personid) {
            throw new coding_exception('Alias already exists for another person');
        }
    }

    /**
     * Links the various activity sources with the known persons, using the aliases
     */
    public static function update_aliases() {
        global $DB;

        $dbfamily = $DB->get_dbfamily();

        if ($dbfamily == 'postgres' or $dbfamily == 'mssql') {
            $sql = "UPDATE {dev_git_commits}
                       SET personid = a.personid
                      FROM {dev_person_aliases} a
                     WHERE a.fullname = {dev_git_commits}.authorname
                       AND a.email = {dev_git_commits}.authoremail";

        } else if ($dbfamily == 'mysql') {
            $sql = "UPDATE {dev_git_commits} c, {dev_person_aliases} a
                       SET c.personid = a.personid
                     WHERE a.fullname = c.authorname
                       AND a.email = c.authoremail";

        } else {
            $sql = "UPDATE {dev_git_commits}
                       SET authorid = (SELECT personid
                                         FROM {dev_person_aliases}
                                        WHERE authorname = {dev_git_commits}.authorname
                                          AND authoremail = {dev_git_commits.authoremail})";
        }

        $DB->execute($sql);
    }
}


/**
 * Used to display the list of unknown persons
 */
class dev_unknown_persons_list implements renderable {

    /** @var array */
    protected $persons = array();

    /** @var null|array */
    protected $menu = null;

    /**
     * Adds the given user data to the list of unknown persons
     *
     * @param string $fullname the fullname of the person in the tracked source
     * @param string $email the email address of the person in the tracked source
     * @param string $source the tracked source identification, eg 'moodle.git' or 'tracker'
     * @param string $info additional information to display, eg number of commits
     */
    public function add_person($fullname, $email, $source, $info=null) {
        $person = new stdClass();
        $person->fullname = $fullname;
        $person->email = $email;
        $person->source = $source;
        $person->info = $info;
        $this->persons[] = $person;
    }

    /**
     * Returns the list of added unknown persons
     *
     * @return array
     */
    public function get_persons() {
        return $this->persons;
    }

    /**
     * Loads the list of currently known persons from the database
     *
     * @return array
     */
    public function get_menu() {
        global $DB;

        if (is_null($this->menu)) {
            $this->menu = array();
            $known = $DB->get_records('dev_persons', null, 'lastname,firstname,email,id', 'id,lastname,firstname,email');
            if (is_array($known)) {
                foreach ($known as $person) {
                    $this->menu[$person->id] = s(sprintf('%s <%s>', fullname($person), $person->email));
                }
            }
        }
        return $this->menu;
    }
}
