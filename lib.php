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
function local_dev_extends_navigation(global_navigation $navigation) {
    global $CFG;

    if (!empty($CFG->hidelocaldevfromnavigation)) {
        return;
    }

    if (!defined('LOCAL_DEV_LOCALLIB_LOADED')) {
        return;
    }

    $icon = new pix_icon('icon', get_string('pluginname', 'local_dev'), 'local_dev');
    $devnode = $navigation->add(get_string('pluginname', 'local_dev'), null, navigation_node::TYPE_CUSTOM, null, 'local_dev-root', $icon);
    $devnode->add(get_string('developers', 'local_dev'), new local_dev_url('/local/dev/index.php'), navigation_node::TYPE_CUSTOM, null, 'local_dev-developers', $icon);
    $devnode->add(get_string('contributions', 'local_dev'), new local_dev_url('/local/dev/contributions.php'), navigation_node::TYPE_CUSTOM, null, 'local_dev-contributions');
    if (has_capability('local/dev:manage', context_system::instance())) {
        $admin = $devnode->add(get_string('administration'));
        $admin->add(get_string('adminoverview', 'local_dev'), new local_dev_url('/local/dev/admin/index.php'));
        $admin->add(get_string('gitaliases', 'local_dev'), new local_dev_url('/local/dev/admin/git-aliases.php'));
    }
}
