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
 * The renderer for the local_dev plugin is defined here
 *
 * @package     local_dev
 * @category    output
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the renderer for the Developers plugin
 */
class local_dev_renderer extends plugin_renderer_base {

    /**
     * @param stdClass $commit data
     * @return string
     */
    public function git_commit($commit) {

        $hash = html_writer::link($commit->urlcommit, s($commit->commithash));
        if (!empty($commit->urlauthor)) {
            $author = html_writer::link($commit->urlauthor, s($commit->author));
        } else {
            $author = s($commit->author);
        }
        $text = sprintf(s("commit %s
Author: %s <%s>
Date:   %s

    %s

"), $hash, $author, s($commit->email), s(date('r', $commit->authordate)), format_string(s($commit->subject)));
        return html_writer::tag('pre', $text, array('class' => 'gitcommit'));
    }
}
