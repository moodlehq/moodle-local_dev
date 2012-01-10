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
 * Puts AMOS into the global navigation tree.
 *
 * @param global_navigation $navigation the navigation tree instance
 * @category navigation
 */
function dev_extends_navigation(global_navigation $navigation) {
    $devnode = $navigation->add(get_string('pluginname', 'local_dev'), new moodle_url('/local/dev/'));
    if (has_capability('local/dev:manage', context_system::instance())) {
        $admin = $devnode->add(get_string('administration'));
        $admin->add(get_string('aliases', 'local_dev'), new moodle_url('/local/dev/admin/aliases.php'));
    }
}
