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
 * @package     local_dev
 * @copyright   2011 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adminoverview'] = 'Overview';
$string['allversions'] = 'All versions';
$string['allversionsonbranch'] = '{$a} (all)';
$string['cachedef_gitcommits'] = 'Git commits aggregations';
$string['contributions'] = 'Contributions';
$string['contributionsdetails'] = 'Details';
$string['contributionsversion'] = 'Contributions to Moodle {$a}';
$string['contributionsversionall'] = 'Contributions to Moodle';
$string['contributionsversioninvalid'] = 'Version {$a} is not valid, please choose the valid version';
$string['contributionsversionselect'] = 'Display report for Moodle version';
$string['dev:manage'] = 'Manage the Developers plugin';
$string['developers'] = 'Developers';
$string['developersinfo'] = 'Many thanks to everyone who contributed to developing Moodle, whether it be coding, testing, writing documentation, coming up with ideas or even just helping other people in the forums. On this page is a list of developers who have contributed directly to core Moodle code. For more details see the <a href="{$a}">Contributions</a> pages.';
$string['gitaliases'] = 'Git aliases';
$string['gitaliasesassign'] = 'Assign user ID';
$string['gitaliasescommits'] = 'Commits';
$string['gitaliasesconflict'] = 'The alias already exists for another user';
$string['gitaliasesemail'] = 'Email';
$string['gitaliasesfullname'] = 'Name';
$string['gitcommits'] = 'Git commits';
$string['gitcommitsby'] = 'Git commits in Moodle {$a->version} by {$a->author}';
$string['gitmerges'] = 'Git merges';
$string['gitmergesby'] = 'Git merges in Moodle {$a->version} by {$a->author}';
$string['myprofilecattitle'] = 'Moodle core contributions';
$string['myprofilefirstcommit'] = 'First commit';
$string['myprofilelastcommit'] = 'Last commit';
$string['pluginname'] = 'Developer credits';
$string['privacy:metadata:db:devactivity'] = 'Contains the aggregated activity records';
$string['privacy:metadata:db:devactivity:gitcommits'] = 'Number of Git commits';
$string['privacy:metadata:db:devactivity:gitmerges'] = 'Number of Git merges';
$string['privacy:metadata:db:devactivity:useremail'] = 'User email';
$string['privacy:metadata:db:devactivity:userfirstname'] = 'User first name';
$string['privacy:metadata:db:devactivity:userlastname'] = 'User last name';
$string['privacy:metadata:db:devactivity:version'] = 'Moodle version this activity should be considered as a part of';
$string['privacy:metadata:db:devgitcommits'] = 'Stores the Git commit records';
$string['privacy:metadata:db:devgitcommits:authordate'] = 'The timestamp of the commit authorship';
$string['privacy:metadata:db:devgitcommits:authoremail'] = 'The commit author\'s email as recorded in the commit object';
$string['privacy:metadata:db:devgitcommits:authorname'] = 'The commit\'s author name as recorded in the commit';
$string['privacy:metadata:db:devgitcommits:commithash'] = 'SHA hash of the commit object';
$string['privacy:metadata:db:devgitcommits:issue'] = 'The tracker issue key this commit is associated with';
$string['privacy:metadata:db:devgitcommits:merge'] = 'Is the commit a merge commit or not';
$string['privacy:metadata:db:devgitcommits:repository'] = 'The name of the repository this commit comes from';
$string['privacy:metadata:db:devgitcommits:subject'] = 'The commit message subject';
$string['privacy:metadata:db:devgitcommits:tag'] = 'The most recent git tag containing this commit';
$string['privacy:metadata:db:devgituseraliases'] = 'Lists all aliases the given user uses in Git repositories';
$string['privacy:metadata:db:devgituseraliases:email'] = 'The email used by the given developer';
$string['privacy:metadata:db:devgituseraliases:fullname'] = 'The name used by the given developer';
$string['privacy:metadata:db:devtrackeractivities'] = 'The activity stream items from the Moodle tracker';
$string['privacy:metadata:db:devtrackeractivities:category'] = 'The category of the activity, if reported by the stream';
$string['privacy:metadata:db:devtrackeractivities:link'] = 'The URL to the activity';
$string['privacy:metadata:db:devtrackeractivities:timecreated'] = 'When did the activity happened';
$string['privacy:metadata:db:devtrackeractivities:title'] = 'The activity title';
$string['privacy:metadata:db:devtrackeractivities:userfullname'] = 'Full name as read from the tracker';
$string['privacy:metadata:db:devtrackeractivities:userlink'] = 'The URL to see the user\'s profile';
$string['privacy:metadata:db:devtrackeractivities:uuid'] = 'The ID of the activity author from the user table, if known';
$string['statsdevs'] = '{$a} developers';
$string['statscommits'] = '{$a} commits';
$string['statscountries'] = '{$a} countries';
