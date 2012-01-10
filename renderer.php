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
     * Renders the list of unknown persons together with the widgets to create a new person
     * or map the unknown person to a known one
     *
     * @param dev_unknown_persons_list $persons
     * @return string
     */
    protected function render_dev_unknown_persons_list(dev_unknown_persons_list $persons) {

        $table = new html_table();
        $table->head = array(
            get_string('aliasesfullname', 'local_dev'),
            get_string('aliasesemail', 'local_dev'),
            get_string('aliasesinfo', 'local_dev'),
            get_string('aliasesmap', 'local_dev'),
            get_string('aliasescreate', 'local_dev')
        );
        $menu = $persons->get_menu();
        foreach ($persons->get_persons() as $person) {
            $table->data[] = array(
                s($person->fullname),
                s($person->email),
                s($person->info.' '.$person->source),
                $this->person_quick_alias_form($person->fullname, $person->email, $menu),
                $this->person_quick_create_form($person->fullname, $person->email),
            );
        }
        return html_writer::table($table);
    }

    // end of API //////////////////////////////////////////////////////////////

    private function person_quick_alias_form($fullname, $email, $menu) {
        if (empty($menu)) {
            return '-';
        }
        $selected = '';
        foreach ($menu as $personid => $personsignature) {
            if (strpos($personsignature, s($email)) !== false) {
                $selected = $personid;
            }
            if ($selected === '' and strpos($personsignature, s($fullname)) !== false) {
                $selected = $personid;
            }
        }
        $form  = html_writer::start_tag('form', array('action' => 'aliases.php', 'method' => 'post', 'class' => 'person-quickmap'));
        $form .= html_writer::start_tag('div');
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'map'));
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'fullname', 'value' => $fullname));
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'email', 'value' => $email));
        $form .= html_writer::select($menu, 'personid', $selected);
        $form .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('personmap', 'local_dev')));
        $form .= html_writer::end_tag('div');
        $form .= html_writer::end_tag('form');
        return $form;
    }

    private function person_quick_create_form($fullname, $email) {
        $sep = strpos($fullname, ' ');
        if ($sep === false) {
            $firstname = '';
            $lastname = '';
        } else {
            $firstname = trim(substr($fullname, 0, $sep));
            $lastname = trim(substr($fullname, $sep+1));
        }
        $cleanemail = clean_param($email, PARAM_EMAIL);

        $form  = html_writer::start_tag('form', array('action' => 'aliases.php', 'method' => 'post', 'class' => 'person-quickadd'));
        $form .= html_writer::start_tag('div');
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'new'));
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'fullname', 'value' => $fullname));
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'email', 'value' => $email));
        $form .= html_writer::empty_tag('input', array('type' => 'text', 'name' => 'firstname', 'size' => 8, 'value' => $firstname));
        $form .= html_writer::empty_tag('input', array('type' => 'text', 'name' => 'lastname', 'size' => 8, 'value' => $lastname));
        $form .= html_writer::empty_tag('input', array('type' => 'text', 'name' => 'cleanemail', 'size' => 8, 'value' => $cleanemail));
        $form .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('personnew', 'local_dev')));
        $form .= html_writer::end_tag('div');
        $form .= html_writer::end_tag('form');
    return $form;
    }
}
