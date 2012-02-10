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
 * Provides table class definitions for the output
 *
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/tablelib.php');

/**
 * This table is used to display the contents of the dev_activity table
 */
class dev_activity_table_sql extends table_sql {

    /** @var string */
    public $useridfield = 'userid';

    /**
     * @param stdClass $data row data
     * @return string HTML for the userpic column
     */
    public function col_userpic($data) {
        global $OUTPUT;
        static $counter = 0;

        if (is_null($data->realuserid)) {
            $src = $OUTPUT->pix_url('u/f2');
            $attributes = array('src' => $src, 'class' => 'userpicture unknownuserpic x'.$counter++, 'width' => 25, 'height' => 25);
            return html_writer::empty_tag('img', $attributes);;

        } else {
            $user = user_picture::unalias($data, null, "realuserid", "realuser");
            return $OUTPUT->user_picture($user, array('size' => 25));
        }
    }

    /**
     * @param stdClass $data row data
     * @return string HTML for the fullname column
     */
    public function col_fullname($data) {

        if (is_null($data->realuserid)) {
            $user = new stdClass();
            $user->firstname = $data->firstname;
            $user->lastname = $data->lastname;

        } else {
            $user = user_picture::unalias($data, null, "realuserid", "realuser");
        }

        return fullname($user);
    }

    /**
     * @param stdClass $data row data
     * @return string HTML for the country column
     */
    public function col_realusercountry($data) {

        if (empty($data->realusercountry)) {
            return '';

        } else {
            return get_string($data->realusercountry, 'core_countries');
        }
    }

    /**
     * @param stdClass $data row data
     * @return string HTML for the column
     */
    public function col_gitcommits($data) {

        if (empty($data->gitcommits)) {
            return $data->gitcommits;

        } else {
            if (empty($data->realuserid)) {
                return html_writer::link(new local_dev_url('/local/dev/gitcommits.php', array(
                    'version' => $data->version,
                    'lastname' => $data->lastname,
                    'firstname' => $data->firstname,
                    'email' => $data->email,
                    'merges' => 0,
                )), $data->gitcommits);

            } else {
                return html_writer::link(new local_dev_url('/local/dev/gitcommits.php', array(
                    'version' => $data->version,
                    'userid' => $data->realuserid,
                    'merges' => 0,
                )), $data->gitcommits);
            }
        }
    }

    /**
     * @param stdClass $data row data
     * @return string HTML for the column
     */
    public function col_gitmerges($data) {

        if (empty($data->gitmerges)) {
            return $data->gitmerges;

        } else {
            if (empty($data->realuserid)) {
                return html_writer::link(new local_dev_url('/local/dev/gitcommits.php', array(
                    'version' => $data->version,
                    'lastname' => $data->lastname,
                    'firstname' => $data->firstname,
                    'email' => $data->email,
                    'merges' => 1,
                )), $data->gitmerges);

            } else {
                return html_writer::link(new local_dev_url('/local/dev/gitcommits.php', array(
                    'version' => $data->version,
                    'userid' => $data->realuserid,
                    'merges' => 1,
                )), $data->gitmerges);
            }
        }
    }
}
