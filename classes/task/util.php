<?php
// This file is part of Moodle - https://moodle.org/
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
 * Provides the {@link local_dev\task\util} class.
 *
 * @package     local_dev
 * @category    task
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dev\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/dev/vendor/autoload.php');
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Utility class for the plugin's scheduled tasks.
 *
 * @copyright 2018 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {

    /**
     * Registers git commits and their branches in the database
     *
     * @param array $options
     */
    public static function git_commits(array $options = []) {
        global $CFG;

        $options['reset-startpoints'] = $options['reset-startpoints'] ?? false;
        $options['show-progress'] = $options['show-progress'] ?? false;

        // This is supposed to be a bare mirror clone of moodle.git.
        $git = new \CzProject\GitPhp\Git;
        $repo = $git->open($CFG->dataroot.'/local_dev/repos/moodle.git');

        $config = get_config('local_dev');

        if ($options['reset-startpoints'] || empty($config->gitstartpoints)) {
            set_config('gitstartpoints', json_encode([]), 'local_dev');
            $config = get_config('local_dev');
        }

        $repo->execute('remote', 'update');

        $gitbranches = array();
        $recentstable = 0;
        foreach ($repo->getBranches() as $gitbranch) {
            if ($gitbranch === 'master') {
                $gitbranches[] = $gitbranch;
                continue;
            }
            if (preg_match('~^MOODLE_([0-9]+)_STABLE$~', $gitbranch, $matches)) {
                $gitbranches[] = $gitbranch;
                if ($matches[1] > $recentstable) {
                    $recentstable = $matches[1];
                }
                continue;
            }
        }

        if ($recentstable < 22) {
            throw new \coding_exception('Something is wrong with the tracked repository, MOODLE_22_STABLE branch not found');
        }

        foreach ($repo->getBranches() as $gitbranch) {
            if ($gitbranch === 'master') {
                $branch = sprintf('MOODLE_%d_STABLE', $recentstable + 1);
            } else {
                $branch = $gitbranch;
            }

            static::git_commits_record($repo, $gitbranch, $branch, 'no-merges', $options['show-progress']);
            static::git_commits_record($repo, $gitbranch, $branch, 'merges', $options['show-progress']);
        }

        // Invalidate the cache used to display the devs names tag-cloud.
        \cache::make('local_dev', 'gitcommits')->purge();
    }

    /**
     * Registers the commit info for all new commits on the given branch
     *
     * @param \CzProject\GitPhp\GitRepository $repo repository to parse
     * @param string $gitbranch the real name of the branch to analyze (eg 'master')
     * @param string $branch the future name of the same branch (eg 'MOODLE_28_STABLE')
     * @param string $mergemode either 'merges' or 'no-merges'
     * @param bool $showprogress
     * @internal
     */
    protected static function git_commits_record(\CzProject\GitPhp\GitRepository $repo, $gitbranch, $branch,
            $mergemode, $showprogress=false) {
        global $DB;

        $startpoints = get_config('local_dev', 'gitstartpoints');
        if ($startpoints === false) {
            set_config('gitstartpoints', json_encode([]), 'local_dev');
            $startpoints = get_config('local_dev', 'gitstartpoints');
        }
        $startpoints = json_decode($startpoints, true);

        $reponame = basename($repo->getRepositoryPath());
        $exclude = empty($startpoints[$branch][$mergemode]) ? '' : $startpoints[$branch][$mergemode];

        if ($mergemode === 'merges') {
            if ($showprogress) {
                mtrace("Searching merges on {$gitbranch} ({$branch})" . ($exclude ? " from {$exclude}" : ""));
            }
            $mergeflag = 1;
        } else if ($mergemode === 'no-merges') {
            if ($showprogress) {
                mtrace("Searching non-merges on {$gitbranch} ({$branch})" . ($exclude ? " from {$exclude}" : ""));
            }
            $mergeflag = 0;
        }

        $exclude = empty($exclude) ? null : '^'.$exclude;

        $commits = $repo->execute('rev-list', '--reverse', '--' . $mergemode,  '--format=tformat:COMMIT:%H TIMESTAMP:%at ' .
            'AUTHORNAME:%an AUTHOREMAIL:%ae SUBJECT:%s', $gitbranch, $exclude);

        $total = floor(count($commits) / 2);
        $counter = 0;

        if ($showprogress and $total == 0) {
            mtrace('no commits found');
        }

        foreach ($commits as $commit) {
            $pattern = '/^COMMIT:([0-9a-f]{40}) TIMESTAMP:([0-9]+) AUTHORNAME:(.+) AUTHOREMAIL:(.+) SUBJECT:(.*)$/';
            if (!preg_match($pattern, $commit, $matches)) {
                continue;
            }

            $record = (object)[
                'repository' => $reponame,
                'commithash' => $matches[1],
                'authordate' => $matches[2],
                'authorname' => $matches[3],
                'authoremail' => $matches[4],
                'subject' => $matches[5],
                'merge' => $mergeflag,
            ];

            $record = @fix_utf8($record);

            // Register the commit info record if it does not exist yet.
            $existing = $DB->get_record('dev_git_commits', ['repository' => $reponame, 'commithash' => $record->commithash],
                'id', IGNORE_MISSING);

            if ($existing === false) {
                $commitid = $DB->insert_record('dev_git_commits', $record, true, true);
            } else {
                $commitid = $existing->id;
            }

            // Register the branch containing the current commit.
            if (! $DB->record_exists('dev_git_commit_branches', array('branch' => $branch, 'commitid' => $commitid))) {
                $branchinfo = (object)[
                    'commitid' => $commitid,
                    'branch' => $branch,
                ];
                $DB->insert_record('dev_git_commit_branches', $branchinfo, false, true);
            }

            if ($showprogress) {
                mtrace(++$counter.'/'.$total, "\r");
            }

            $startpoints[$branch][$mergemode] = $record->commithash;

            if ($counter % 1000 == 0) {
                set_config('gitstartpoints', json_encode($startpoints), 'local_dev');
            }
        }

        set_config('gitstartpoints', json_encode($startpoints), 'local_dev');

        if ($showprogress) {
            mtrace('done');
        }
    }

    /**
     * Records the most recent tag containing the commit in the database
     *
     * This basically calls `git describe --contains` on each commit in the database
     * and stores the result. Only the version release tags are supported (ie those
     * starting with the "v" character followed by a digit).
     *
     * @param array $options
     */
    public static function git_tags(array $options=[]) {
        global $CFG, $DB;

        $options['show-progress'] = $options['show-progress'] ?? false;

        $git = new \CzProject\GitPhp\Git;
        $repo = $git->open($CFG->dataroot.'/local_dev/repos/moodle.git');

        $commits = $DB->get_fieldset_select('dev_git_commits', 'commithash', 'tag IS NULL ORDER BY authordate DESC');
        $total = count($commits);
        $counter = 0;

        foreach ($commits as $commit) {
            try {
                $tag = implode($repo->execute('describe', '--exact-match', '--match', 'v[0-9]*', '--contains', $commit));
                if (preg_match('/^(v[0-9]+\.[0-9]+.*?)~.*$/', $tag, $matches)) {
                    $tag = $matches[1];
                    $DB->set_field('dev_git_commits', 'tag', $tag, array('commithash' => $commit));
                }

            } catch (\CzProject\GitPhp\GitException $e) {
                $error = implode($e->getRunnerResult()->getErrorOutput());

                // The "fatal: cannot describe" error means there is no tag yet describing this commit.
                // All others are real errors.
                if (strpos($error, 'cannot describe') === null) {
                    throw $e;
                }
            }

            if ($options['show-progress']) {
                mtrace(++$counter.'/'.$total, "\r");
            }
        }

        if ($options['show-progress']) {
            mtrace('done');
        }
    }

    /**
     * Update the "Developers" cohort members with Moodle core contributors.
     */
    public static function sync_cohort() {
        global $DB;

        $cohort = (object)[
            'idnumber' => 'local_dev:Developers',
            'component' => 'local_dev',
        ];

        if ($existingcohort = $DB->get_record('cohort', (array)$cohort)) {
            $cohort = $existingcohort;
            // Populate cohort members array based on existing members.
            $members = $DB->get_records('cohort_members', ['cohortid' => $cohort->id], 'userid', 'userid');

        } else {
            $cohort->contextid = context_system::instance()->id;
            $cohort->name = 'Developers';
            $cohort->description = 'Automatically generated cohort from developer plugin for [Developers]';
            $cohort->id = cohort_add_cohort($cohort);
            $members = [];
        }

        $sql = "SELECT DISTINCT userid
                  FROM {dev_git_commits}
                 WHERE userid IS NOT NULL
              ORDER BY userid";
        $rs = $DB->get_recordset_sql($sql);

        foreach ($rs as $record) {
            if (!isset($members[$record->userid])) {
                cohort_add_member($cohort->id, $record->userid);
                $members[$record->userid] = $record->userid;
            }
        }

        $rs->close();
    }
}
