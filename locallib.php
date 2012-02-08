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
 * Provides various classes used by the plugin
 *
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

interface aggregable {
    public function on_execute();
}

/**
 * Manages activity aggregation
 *
 * Every activity subsystem (Git, tracker etc) can hold its own in its tables and eventually
 * provide detailed report of it. However, all subsystems are supposed to be able to aggregate
 * their data into a rough report of the user's amount of activity per Moodle release.
 */
class dev_aggregator {

    /** @var array of aggregatable classes to  */
    protected $sources;

    /**
     * Registers new aggregation source
     *
     * @param string $name
     */
    public function add_source($name) {
        $classname = 'dev_'.$name.'_aggregator';
        if (!class_exists($classname)) {
            throw new coding_exception('The given class does not exist', $classname);
        }
        if (!in_array('aggregable', class_implements($classname))) {
            throw new coding_exception('The given class does not implement aggregable interface', $classname);
        }
        $this->sources['name'] = new $classname($this);
    }

    /**
     * Executes all aggregators
     */
    public function execute() {
        foreach ($this->sources as $name => $aggregator) {
            $aggregator->on_execute();
        }
    }
}

/**
 * Base class for all aggregable subsystems
 */
abstract class dev_aggregator_subsystem implements aggregable {

    /** @var dev_aggregator the master aggregator class that executes aggregation */
    protected $parentaggregator;

    /**
     * @param dev_aggregator $pardev_aggregator_subsystementaggregator the class that runs the execution
     */
    public function __construct(dev_aggregator $parentaggregator) {
        $this->parentaggregator = $parentaggregator;
    }

    /**
     * Updates the activity record or registers a new one
     *
     * @param stdClass $activity
     * @return int the record id
     */
    protected function update_activity(stdClass $activity) {
        global $DB;

        if (empty($activity->type)
                or empty($activity->version)
                or (empty($activity->userid) and empty($activity->useremail))) {
            throw new coding_exception('Missing activity data', json_encode($activity));
        }

        $conditions = array('type' => $activity->type, 'version' => $activity->version);

        if (isset($activity->userid)) {
             $conditions['userid'] = $activity->userid;
        } else {
            $conditions['userfirstname'] = $activity->userfirstname;
            $conditions['userlastname'] = $activity->userlastname;
            $conditions['useremail'] = $activity->useremail;
        }

        $current = $DB->get_record('dev_activity', $conditions, '*', IGNORE_MISSING);

        if (!$current) {
            $id = $DB->insert_record('dev_activity', $activity);
        } else {
            $id = $current->id;
            if ($activity->amount <> $current->amount) {
                $current->amount = $activity->amount;
                $DB->update_record('dev_activity', $current);
            }
        }

        return $id;
    }
}

/**
 * Aggregates number of commits in moodle.git repository
 */
class dev_git_aggregator extends dev_aggregator_subsystem {

    /**
     * @inheritdoc
     */
    public function on_execute() {
        global $DB;

        // aggregate the number of commits into a big in-memory array first

        $sql = "SELECT c.tag, u.id, c.authorname, c.authoremail, COUNT(*) AS commits
                  FROM {dev_git_commits} c
             LEFT JOIN {user} u ON c.userid = u.id
                 WHERE c.tag <> ''
              GROUP BY c.tag, u.id, c.authorname, c.authoremail";

        $rs = $DB->get_recordset_sql($sql);
        $commits = array();
        $unknownusers = array();
        foreach ($rs as $record) {
            $userid = $this->calculate_user_id($record);
            if (!is_numeric($userid) and !isset($unknownusers[$userid])) {
                $unknownusers[$userid] = $this->calculate_user_details($record);
            }
            $version = $this->tag_to_version($record->tag);
            if (!isset($commits[$userid][$version])) {
                $commits[$userid][$version] = 0;
            }
            $commits[$userid][$version] += $record->commits;
        }
        $rs->close();

        // update the aggregated values in the database

        $legacy = $DB->get_fieldset_select('dev_activity', 'id', "type = 'git'");
        $legacy = array_flip($legacy);

        foreach ($commits as $userid => $versions) {
            foreach ($versions as $version => $amount) {
                $activity = new stdClass();
                $activity->type = 'git';
                $activity->version = $version;
                if (is_numeric($userid)) {
                    $activity->userid = $userid;
                } else {
                    $activity->userfirstname = $unknownusers[$userid]->firstname;
                    $activity->userlastname = $unknownusers[$userid]->lastname;
                    $activity->useremail = $unknownusers[$userid]->email;
                }
                $activity->amount = $amount;

                $id = $this->update_activity($activity);
                if (isset($legacy[$id])) {
                    // this activity id is up-to-date now
                    unset($legacy[$id]);
                }
            }
        }

        // remove all legacy activity records
        $DB->delete_records_list('dev_activity', 'id', array_keys($legacy));
    }

    /**
     * Calculates the identificator of the activity user
     *
     * @param stdClass $record with id, authorname and authoremail
     * @return int|string
     */
    protected function calculate_user_id(stdClass $record) {
        if (is_numeric($record->id)) {
            return $record->id;
        } else {
            return 'user_'.sha1($record->authorname.'#!@@$%#'.$record->authoremail);
        }
    }

    /**
     * Tries to construct a nice firstname, lastname and email
     *
     * @param stdClass $record with authorname and authoremail
     * @return stdClass with firstname, lastname and email
     */
    protected function calculate_user_details(stdClass $record) {
        $user = new stdClass();
        $user->email = $record->authoremail;
        $authorname = trim($record->authorname);
        $sep = strrpos($authorname, ' ');
        if ($sep === false) {
            $user->firstname = '';
            $user->lastname = $authorname;
        } else {
            $user->firstname = substr($authorname, 0, $sep);
            $user->lastname = substr($authorname, $sep + 1);
        }
        return $user;
    }

    /**
     * Converts a Git tag like 'v2.1.0-rc1' to a plain version like '2.1.0'
     *
     * @param string $tag
     * @return string|null
     */
    protected function tag_to_version($tag) {
        if (preg_match('~^v([0-9]+\.[0-9]+\.[0-9]+)-?~', $tag, $matches)) {
            return $matches[1];
        } else {
            return null;
        }
    }
}

/**
 * Manages Git user aliases
 */
class dev_git_aliases_manager {

    /**
     * Registers new alias
     *
     * @param int $userid the real user ID
     * @param string $fullname the user's name as displayed in Git
     * @param string $email the user's email in Git
     * @return bool|null True for success, false if the alias already exists for another user, null if the alias already exists
     */
    public static function add_alias($userid, $fullname, $email) {
        global $DB;

        if (is_null($userid) or is_null($fullname) or is_null($email)) {
            throw new coding_exception('NULL parameter values not allowed here');
        }

        $existing = $DB->get_record('dev_git_user_aliases', array('fullname' => $fullname, 'email' => $email), 'userid', IGNORE_MISSING);

        if ($existing === false) {
            $alias = new stdClass();
            $alias->userid = $userid;
            $alias->fullname = $fullname;
            $alias->email    = $email;
            $DB->insert_record('dev_git_user_aliases', $alias);
            return true;

        } else if ($existing->userid != $userid) {
            return false;

        } else {
            return null;
        }

    }

    /**
     * Links the Git commit records with the user table, using the user's email and aliases
     */
    public static function update_aliases() {
        global $DB;

        $dbfamily = $DB->get_dbfamily();

        if ($dbfamily == 'postgres' or $dbfamily == 'mssql') {
            $sql = "UPDATE {dev_git_commits}
                       SET userid = u.id
                      FROM {user} u
                     WHERE {dev_git_commits}.userid IS NULL
                           AND u.email = {dev_git_commits}.authoremail";
            $DB->execute($sql);

            $sql = "UPDATE {dev_git_commits}
                       SET userid = a.userid
                      FROM {dev_git_user_aliases} a
                     WHERE {dev_git_commits}.userid IS NULL
                           AND a.fullname = {dev_git_commits}.authorname
                           AND a.email = {dev_git_commits}.authoremail";
            $DB->execute($sql);

        } else if ($dbfamily == 'mysql') {
            $sql = "UPDATE {dev_git_commits} c, {user} u
                       SET c.userid = u.id
                     WHERE u.email = c.authoremail";
            $DB->execute($sql);

            $sql = "UPDATE {dev_git_commits} c, {dev_git_user_aliases} a
                       SET c.userid = a.userid
                     WHERE a.fullname = c.authorname
                       AND a.email = c.authoremail";
            $DB->execute($sql);

        } else {
            $sql = "UPDATE {dev_git_commits}
                       SET userid = (SELECT id
                                       FROM {user}
                                      WHERE authoremail = {user}.email})";
            $DB->execute($sql);

            $sql = "UPDATE {dev_git_commits}
                       SET userid = (SELECT userid
                                       FROM {dev_git_user_aliases}
                                      WHERE authorname = {dev_git_commits}.authorname
                                        AND authoremail = {dev_git_commits}.authoremail)";
            $DB->execute($sql);
        }
    }
}
