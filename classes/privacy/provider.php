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
 * Defines {@link \local_dev\privacy\provider} class.
 *
 * @package     local_dev
 * @category    privacy
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dev\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for the Developer credits plugin.
 *
 * @copyright  2018 David Mudrák <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe all the places where the Developer credits plugin stores some personal data.
     *
     * @param collection $collection Collection of items to add metadata to.
     * @return collection Collection with our added items.
     */
    public static function get_metadata(collection $collection) : collection {

        $collection->add_database_table('dev_activity', [
           'userlastname' => 'privacy:metadata:db:devactivity:userlastname',
           'userfirstname' => 'privacy:metadata:db:devactivity:userfirstname',
           'useremail' => 'privacy:metadata:db:devactivity:useremail',
           'version' => 'privacy:metadata:db:devactivity:version',
           'gitcommits' => 'privacy:metadata:db:devactivity:gitcommits',
           'gitmerges' => 'privacy:metadata:db:devactivity:gitmerges',
        ], 'privacy:metadata:db:devactivity');

        $collection->add_database_table('dev_git_commits', [
           'repository' => 'privacy:metadata:db:devgitcommits:repository',
           'commithash' => 'privacy:metadata:db:devgitcommits:commithash',
           'authordate' => 'privacy:metadata:db:devgitcommits:authordate',
           'authorname' => 'privacy:metadata:db:devgitcommits:authorname',
           'authoremail' => 'privacy:metadata:db:devgitcommits:authoremail',
           'subject' => 'privacy:metadata:db:devgitcommits:subject',
           'merge' => 'privacy:metadata:db:devgitcommits:merge',
           'issue' => 'privacy:metadata:db:devgitcommits:issue',
           'tag' => 'privacy:metadata:db:devgitcommits:tag',
        ], 'privacy:metadata:db:devgitcommits');

        $collection->add_database_table('dev_git_user_aliases', [
           'fullname' => 'privacy:metadata:db:devgituseraliases:fullname',
           'email' => 'privacy:metadata:db:devgituseraliases:email',
        ], 'privacy:metadata:db:devgituseraliases');

        $collection->add_database_table('dev_tracker_activities', [
           'uuid' => 'privacy:metadata:db:devtrackeractivities:uuid',
           'title' => 'privacy:metadata:db:devtrackeractivities:title',
           'userfullname' => 'privacy:metadata:db:devtrackeractivities:userfullname',
           'userlink' => 'privacy:metadata:db:devtrackeractivities:userlink',
           'category' => 'privacy:metadata:db:devtrackeractivities:category',
           'link' => 'privacy:metadata:db:devtrackeractivities:link',
           'timecreated' => 'privacy:metadata:db:devtrackeractivities:timecreated',
        ], 'privacy:metadata:db:devtrackeractivities');

        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for the specified user.
     *
     * @param int $userid ID of the user.
     * @return contextlist List of contexts containing the user's personal data.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {

        $contextlist = new contextlist();
        $contextlist->add_system_context();

        return $contextlist;
    }

    /**
     * Export personal data stored in the given contexts.
     *
     * @param approved_contextlist $contextlist List of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!count($contextlist)) {
            return;
        }

        $syscontextapproved = false;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->id == SYSCONTEXTID) {
                $syscontextapproved = true;
                break;
            }
        }

        if (!$syscontextapproved) {
            return;
        }

        $user = $contextlist->get_user();
        $writer = writer::with_context(\context_system::instance());
        $subcontext = [get_string('pluginname', 'local_dev')];

        $activity = $DB->get_records('dev_activity', ['userid' => $user->id], '', 'id, version, gitcommits, gitmerges');
        if ($activity) {
            $writer->export_data($subcontext, (object) ['contributions' => array_values(array_map(function($record) {
                unset($record->id);
                return $record;
            }, $activity))]);
            unset($activity);
        }

        $commits = $DB->get_records('dev_git_commits', ['userid' => $user->id], 'repository, authordate',
            'id, repository, commithash, authordate, authorname, authoremail, subject, merge, issue, tag');
        if ($commits) {
            $writer->export_related_data($subcontext, 'gitcommits', array_values(array_map(function($record) {
                unset($record->id);
                $record->authordate = transform::datetime($record->authordate);
                return $record;
            }, $commits)));
            unset($commits);
        }

        $aliases = $DB->get_records('dev_git_user_aliases', ['userid' => $user->id], '',
            'id, fullname, email');
        if ($aliases) {
            $writer->export_related_data($subcontext, 'aliases', array_values(array_map(function($record) {
                unset($record->id);
                return $record;
            }, $aliases)));
            unset($aliases);
        }

        $tracker = $DB->get_records('dev_tracker_activities', ['userid' => $user->id], '',
            'id, uuid, title, userfullname, userlink, category, link, timecreated');
        if ($tracker) {
            $writer->export_related_data($subcontext, 'tracker', array_values(array_map(function($record) {
                unset($record->id);
                return $record;
            }, $tracker)));
            unset($tracker);
        }
    }

    /**
     * Delete personal data for all users in the context.
     *
     * @param context $context Context to delete personal data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // Not implemented yet.
    }

    /**
     * Delete personal data for the user in a list of contexts.
     *
     * @param approved_contextlist $contextlist List of contexts to delete data from.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // Not implemented yet.
    }
}
