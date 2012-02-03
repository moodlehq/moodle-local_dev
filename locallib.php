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
