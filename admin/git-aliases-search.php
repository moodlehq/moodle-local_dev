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
 * AJAX search for the user at the Git aliases page
 *
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_login(SITEID, false);

if (!has_capability('local/dev:manage', context_system::instance())) {
    header('HTTP/1.1 403 Missing capability');
    die();
}
if (!confirm_sesskey()) {
    header('HTTP/1.1 403 Missing sesskey');
    die();
}

$query = required_param('q', PARAM_TEXT);

header('Content-Type: application/json; charset: utf-8');
$response = new stdClass();
$response->status = 'ok';
$response->query = $query;
$response->results = array();

if (!empty($query)) {

    $sql = "SELECT id, firstname, lastname, email
              FROM {user}
             WHERE ".$DB->sql_like("firstname", "?", false, false)."
                   OR ".$DB->sql_like("lastname", "?", false, false)."
                   OR ".$DB->sql_like("email", "?", false, false);

    $parts = explode(' ', $query);
    $parts = array_map('trim', $parts);
    $parts = array_filter($parts);

    if (count($parts) == 2) {
        $sql = "SELECT id, firstname, lastname, email
                  FROM {user}
                 WHERE ".$DB->sql_like("firstname", "?", false, false)."
                       AND ".$DB->sql_like("lastname", "?", false, false);
        $params = array(
            $DB->sql_like_escape($parts[0]).'%',
            $DB->sql_like_escape($parts[1]).'%'
        );
    } else {
        $sql = "SELECT id, firstname, lastname, email
                  FROM {user}
                 WHERE ".$DB->sql_like("firstname", "?", false, false)."
                       OR ".$DB->sql_like("lastname", "?", false, false)."
                       OR ".$DB->sql_like("email", "?", false, false);
        $params = array(
            '%'.$DB->sql_like_escape($query).'%',
            '%'.$DB->sql_like_escape($query).'%',
            '%'.$DB->sql_like_escape($query).'%'
        );
    }

    $rs = $DB->get_recordset_sql($sql, $params, 0, 10);

    foreach ($rs as $r) {
        $response->results[] = (object)array(
            'userid'    => $r->id,
            'firstname' => $r->firstname,
            'lastname'  => $r->lastname,
            'email'     => $r->email,
            'signature' => sprintf('%s <%s>', fullname($r), $r->email),
        );
    }

    $rs->close();
}

echo json_encode($response);
