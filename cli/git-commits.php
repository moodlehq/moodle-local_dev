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
 * Registers git commits and their branches in the database
 *
 * @package     local_dev
 * @copyright   2011 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/local/dev/lib/php-git-repo/lib/PHPGit/Repository.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(array(
    'help' => false,
    'show-progress' => false,
    'reset-startpoints' => false,
), array('h' => 'help'));

if ($options['help']) {
    fputs(STDOUT, "Registers git commits and their branches in the database

--show-progress         Display the progress indicator
--reset-startpoints     Re-start from the first commit in the repository
--help                  Display this help

");
    exit(0);
}

fputs(STDOUT, "*****************************************\n");
fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " GIT-COMMITS JOB STARTED\n");

$repo = new PHPGit_Repository($CFG->dataroot.'/local_dev/repos/moodle.git');

$config = get_config('local_dev');

if ($options['reset-startpoints'] or empty($config->startpoints)) {
    set_config('startpoints', json_encode(array()), 'local_dev');
    $config = get_config('local_dev');
}

$repo->git('remote update');

foreach ($repo->getBranches() as $gitbranch) {
    if ($gitbranch === 'master') {
        // todo some sort of auto-mapping here
        $branch = 'MOODLE_23_STABLE';
    } else {
        $branch = $gitbranch;
    }

    dev_git_record_commits($repo, $gitbranch, $branch, 'no-merges', $options['show-progress']);
    dev_git_record_commits($repo, $gitbranch, $branch, 'merges', $options['show-progress']);

}

fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " GIT-COMMITS JOB DONE\n");
exit(0);


/**
 * Registers the commit info for all new commits on the given branch
 *
 * @param PHPGit_Repository $repo repository to parse
 * @param string $gitbranch the real name of the branch to analyze (eg 'master')
 * @param string $branch the future name of the same branch (eg 'MOODLE_28_STABLE')
 * @param string $mergemode either 'merges' or 'no-merges'
 * @param bool $showprogress
 * @internal
 */
function dev_git_record_commits(PHPGit_Repository $repo, $gitbranch, $branch, $mergemode, $showprogress=false) {
    global $DB;

    $startpoints = get_config('local_dev', 'startpoints');
    if ($startpoints === false) {
        set_config('startpoints', json_encode(array()), 'local_dev');
        $startpoints = get_config('local_dev', 'startpoints');
    }
    $startpoints = json_decode($startpoints, true);

    $reponame = basename($repo->getDir());
    $exclude = empty($startpoints[$branch][$mergemode]) ? '' : $startpoints[$branch][$mergemode];

    if ($mergemode === 'merges') {
        fputs(STDOUT, "Searching merges on {$gitbranch} ({$branch})" . ($exclude ? " from {$exclude}" : "") . PHP_EOL);
        $mergeflag = 1;
    } else if ($mergemode === 'no-merges') {
        fputs(STDOUT, "Searching non-merges on {$gitbranch} ({$branch})" . ($exclude ? " from {$exclude}" : "") . PHP_EOL);
        $mergeflag = 0;
    }

    $exclude = empty($exclude) ? '' : '^'.$exclude;

    $commits = explode(PHP_EOL, $repo->git("rev-list --reverse --{$mergemode} --format='tformat:COMMIT:%H TIMESTAMP:%at AUTHORNAME:%an AUTHOREMAIL:%ae SUBJECT:%s' {$gitbranch} {$exclude}"));

    $total = floor(count($commits) / 2);
    $counter = 0;

    if ($showprogress and $total == 0) {
        fputs(STDOUT, 'no commits found');
    }

    foreach ($commits as $commit) {
        $pattern = '/^COMMIT:([0-9a-f]{40}) TIMESTAMP:([0-9]+) AUTHORNAME:(.+) AUTHOREMAIL:(.+) SUBJECT:(.*)$/';
        if (!preg_match($pattern, $commit, $matches)) {
            continue;
        }

        $record = new stdClass();
        $record->repository     = $reponame;
        $record->commithash     = $matches[1];
        $record->authordate     = $matches[2];
        $record->authorname     = $matches[3];
        $record->authoremail    = $matches[4];
        $record->subject        = $matches[5];
        $record->merge          = $mergeflag;

        $record = @fix_utf8($record);

        // register the commit info record if it does not exist yet
        $existing = $DB->get_record('dev_git_commits', array('repository' => $reponame, 'commithash' => $record->commithash), 'id', IGNORE_MISSING);

        if ($existing === false) {
            $commitid = $DB->insert_record('dev_git_commits', $record, true, true);
        } else {
            $commitid = $existing->id;
        }

        // register the branch containing the current commit
        if (! $DB->record_exists('dev_git_commit_branches', array('commitid' => $commitid))) {
            $branchinfo = new stdClass();
            $branchinfo->commitid = $commitid;
            $branchinfo->branch = $branch;
            $DB->insert_record('dev_git_commit_branches', $branchinfo, false, true);
        }

        if ($showprogress) {
            fputs(STDOUT, ++$counter.'/'.$total."\r");
        }

        $startpoints[$branch][$mergemode] = $record->commithash;

        if ($counter % 1000 == 0) {
            set_config('startpoints', json_encode($startpoints), 'local_dev');
        }
    }

    set_config('startpoints', json_encode($startpoints), 'local_dev');

    if ($showprogress) {
        fputs(STDOUT, PHP_EOL);
    }
}
