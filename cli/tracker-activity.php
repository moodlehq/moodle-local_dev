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
 * Fetches the activity data from the Moodle tracker
 *
 * The script consumes activity streams provided by JIRA to the REST web services of the activity stream provided
 *
 * @link        https://developer.atlassian.com/display/STREAMS/Consuming+an+Activity+Streams+Feed
 * @link        http://docs.atlassian.com/rpc-jira-plugin/4.4/com/atlassian/jira/rpc/soap/JiraSoapService.html
 * @link        http://docs.atlassian.com/jira/REST/4.4/
 * @package     local_dev
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

/**
 * The time chunk size in seconds
 */
define('DEV_TRACKER_TIMECHUNKSIZE', 300);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/simplepie/moodle_simplepie.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(array(
    'max-head-requests' => 9999,
    'max-tail-requests' => 10,
    'help' => false,
    'available-filters' => false,
), array('h' => 'help'));

if ($options['help']) {
    fputs(STDOUT, "Registers the activity in the Moodle JIRA tracker

--max-head-requests     The maximum number of requests to fetch and record the most recent activity (9999 by default)
--max-tail-requests     The maximum number of requests to fetch and record the historical activity (10 by default)
--available-filters     Display the list of available filters (used for development)
--help                  Display this help

");
    exit(0);
}

$since = mktime(0, 0, 0, 1, 1, 2000); // Jan 1 2011

if ($options['available-filters']) {
    $curl = new curl();
    $response = $curl->get('https://tracker.moodle.org/rest/activity-stream/1.0/config');
    print_r(json_decode($response));
    exit(0);
}

if ($max = $options['max-head-requests']) {
    // get the timestamp of the most recent activity we know
    $head = $DB->get_field_sql("SELECT MAX(timecreated) FROM {dev_tracker_activities}");
    if (!$head) {
        $head = $since;
    }

    // fill the gap between now and $head
    $hat = time();
    $cnt = 0;
    while($hat > $head and $cnt < $max) {
        dev_register_tracker_activity($hat, $hat - DEV_TRACKER_TIMECHUNKSIZE - 10);
        $cnt++;
        $hat = $hat - DEV_TRACKER_TIMECHUNKSIZE;
    }
}

if ($max = $options['max-tail-requests']) {
    $foot = get_config('local_dev', 'trackerfoot');
    if ($foot === false) {
        $foot = $DB->get_field_sql("SELECT MIN(timecreated) FROM {dev_tracker_activities}");
        if (!$foot) {
            $foot = time();
        }
    }

    // fill the gap at the beginning of the tracked history
    $floor = $since;
    $cnt = 0;
    while($foot > $floor and $cnt < $max) {
        dev_register_tracker_activity($foot, $foot - DEV_TRACKER_TIMECHUNKSIZE - 10);
        $cnt++;
        set_config('trackerfoot', $foot, 'local_dev');
        $foot = $foot - DEV_TRACKER_TIMECHUNKSIZE;
    }
}

/**
 * Fetch the activity stream from the tracker and register the activity items
 *
 * @param int $before optionally filter activity that happened before this timestamp
 * @param int $after optionally filter activity that happened after this timestamp
 * @internal
 */
function dev_register_tracker_activity($before = null, $after = null) {
    global $DB;

    $filters = array();
    $filters[] = 'streams='.rawurlencode('key+IS+MDL+MDLQA+MDLSITE+MOBILE');
    if (!is_null($before) and !is_null($after)) {
        $filters[] = 'streams='.rawurlencode('update-date+BETWEEN+'.($after*1000).'+'.($before*1000));
    } else if (!is_null($before)) {
        $filters[] = 'streams='.rawurlencode('update-date+BEFORE+'.($before*1000));
    } else if (!is_null($after)) {
        $filters[] = 'streams='.rawurlencode('update-date+AFTER+'.($after*1000));
    }

    $url = 'https://tracker.moodle.org/activity?'.implode('&', $filters);

    if (!is_null($after)) {
        fputs(STDOUT, date("Y-m-d H:i:s", $after));
    } else {
        fputs(STDOUT, '*');
    }
    fputs(STDOUT, ' - ');
    if (!is_null($before)) {
        fputs(STDOUT, date("Y-m-d H:i:s", $before));
    } else {
        fputs(STDOUT, '*');
    }

    $feed = new moodle_simplepie();
    $feed->set_timeout(10);
    $feed->set_feed_url($url);
    $feed->init();

    if ($error = $feed->error()) {
        fputs(STDERR, $error.PHP_EOL);
        exit(1);
    }

    $fetched = 0;
    $created = 0;
    foreach ($feed->get_items() as $item) {
        $fetched++;
        $activity = new stdClass();
        $activity->uuid = $item->get_id();
        $activity->title = $item->get_title();
        $activity->timecreated = $item->get_date('U');
        $activity->link = $item->get_link();
        if ($tmp = $item->get_category()) {
            $activity->category = $tmp->get_term();
        }
        if ($tmp = $item->get_author()) {
            $activity->personfullname = $tmp->get_name();
            $activity->personemail = $tmp->get_email();
            $activity->personlink = $tmp->get_link();
        }

        if (!$DB->record_exists('dev_tracker_activities', array('uuid' => $activity->uuid))) {
            $DB->insert_record('dev_tracker_activities', $activity, false, true);
            $created++;
        }
    }

    fputs(STDOUT, sprintf(" %d %d %s\n", $fetched, $created, rawurldecode($url)));
}
