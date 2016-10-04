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
 * The plugin's external API is defined here
 *
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Custom URL handling class that supports the plugin appearing outside its real location
 */
class local_dev_url extends moodle_url {

    /**
     * Given the real location if the script like '/local/dev/file.php' this method
     * actually creates URL to '/dev/file.php'
     */
    public function __construct($url, array $params = null) {

        if (is_string($url) and preg_match("~^/local/dev(/?)(.*)$~", $url, $matches)) {
            $url = '/dev/'.$matches[2];
        }
        parent::__construct($url, $params);
    }
}

/**
 * Puts AMOS into the global navigation tree.
 *
 * @param global_navigation $navigation the navigation tree instance
 * @category navigation
 */
function local_dev_extend_navigation(global_navigation $navigation) {
    global $CFG;

    if (!empty($CFG->hidelocaldevfromnavigation)) {
        return;
    }

    if (!defined('LOCAL_DEV_LOCALLIB_LOADED')) {
        return;
    }

    $devnode = $navigation->add(get_string('pluginname', 'local_dev'), null, navigation_node::TYPE_CUSTOM, null, 'local_dev-root');
    $devnode->add(get_string('developers', 'local_dev'), new local_dev_url('/local/dev/index.php'), navigation_node::TYPE_CUSTOM, null, 'local_dev-developers');
    $devnode->add(get_string('contributions', 'local_dev'), new local_dev_url('/local/dev/contributions.php'), navigation_node::TYPE_CUSTOM, null, 'local_dev-contributions');
    if (has_capability('local/dev:manage', context_system::instance())) {
        $admin = $devnode->add(get_string('administration'));
        $admin->add(get_string('adminoverview', 'local_dev'), new local_dev_url('/local/dev/admin/index.php'));
        $admin->add(get_string('gitaliases', 'local_dev'), new local_dev_url('/local/dev/admin/git-aliases.php'));
    }
}

/**
 * Adds information about contributions into the user profile pages.
 *
 * @param \core_user\output\myprofile\tree $tree Profile tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass $course
 *
 * @return bool
 */
function local_dev_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $DB, $OUTPUT;

    $params = ['userid' => $user->id];

    $sql = "SELECT da.userid, da.gitcommits, da.gitmerges, MIN(dgc.authordate) AS firstcommit, MAX(dgc.authordate) AS lastcommit
              FROM {dev_activity} da
              JOIN {dev_git_commits} dgc ON da.userid = dgc.userid
             WHERE da.version = 'x.x.x' AND da.userid = :userid";

    $data = $DB->get_record_sql($sql, ['userid' => $user->id]);

    if (empty($data->userid)) {
        return;
    }

    $category = new core_user\output\myprofile\category('moodlecore',
        get_string('myprofilecattitle', 'local_dev'), 'contact');
    $tree->add_category($category);

    if ($data->gitcommits) {
        $url = new local_dev_url('/local/dev/gitcommits.php', ['version' => 'x.x.x', 'userid' => $user->id, 'merges' => 0]);
        $numberofcommits = '<span class="badge">'.$data->gitcommits.'</span>';
        $link = html_writer::link($url, get_string('gitcommits', 'local_dev'));
        $tree->add_node(new core_user\output\myprofile\node('moodlecore', 'gitcommits', $link.' '.$numberofcommits));
    }

    if ($data->gitmerges) {
        $url = new local_dev_url('/local/dev/gitcommits.php', ['version' => 'x.x.x', 'userid' => $user->id, 'merges' => 1]);
        $numberofcommits = '<span class="badge">'.$data->gitmerges.'</span>';
        $link = html_writer::link($url, get_string('gitmerges', 'local_dev'));
        $tree->add_node(new core_user\output\myprofile\node('moodlecore', 'gitmerges', $link.' '.$numberofcommits));
    }

    if (($data->gitcommits + $data->gitmerges > 1) and $data->firstcommit) {
        $date = userdate($data->firstcommit, '', core_date::get_user_timezone());
        $date .= ' <small>('.format_time(time() - $data->firstcommit).')</small>';
        $tree->add_node(new core_user\output\myprofile\node('moodlecore', 'firstcommit',
            get_string('myprofilefirstcommit', 'local_dev'), null, null, $date));
    }

    if ($data->lastcommit) {
        $date = userdate($data->lastcommit, '', core_date::get_user_timezone());
        $date .= ' <small>('.format_time(time() - $data->lastcommit).')</small>';
        $tree->add_node(new core_user\output\myprofile\node('moodlecore', 'lastcommit',
            get_string('myprofilelastcommit', 'local_dev'), null, null, $date));
    }

    $creditslink = (new local_dev_url('/local/dev/'))->out();
    $node = new core_user\output\myprofile\node('moodlecore', 'creditslink', get_string('pluginname', 'local_dev'),
        null, $creditslink, null, null, 'viewmore');
    $tree->add_node($node);
}
