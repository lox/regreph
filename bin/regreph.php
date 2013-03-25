<?php

/**
 * regreph.php <testfile> <dir> <revspec>
 */

function get_git_commits($dir, $from, $to)
{
    $commits = array();
    $cmd = "cd {$dir}; git log --pretty=oneline {$from}...{$to}";

    foreach(explode("\n", trim(`$cmd`)) as $line) {
        list($sha1, $descr) = explode(' ', $line, 2);
        $commits[$sha1] = $descr;
    }

    return $commits;
}

function get_commit_log($dir, $sha1)
{
    $cmd = trim("cd {$dir}; git show {$sha1} --pretty=oneline| head -n1");
    list($sha1, $descr) = explode(' ', trim(`$cmd`), 2);

    return $descr;
}

function get_working_dir($dir, $sha1)
{
    $tmpdir = sys_get_temp_dir().'/.'.$sha1;

    // pull down recent git changes
    if(!is_dir($tmpdir)) {
        `git clone -l {$dir} {$tmpdir}`;
    } else {
        `cd $tmpdir; git fetch; git reset --hard {$sha1}`;
    }

    // update composer
    `cd {$tmpdir}; composer install --dev`;

    return $tmpdir;
}

function print_results($results, $last=false)
{
    foreach($results as $method=>$data) {

        $totals = $data['totals'];
        $row = array(
            'calls' => array('value' => number_format($totals['ct']), 'delta'=>''),
            'wall time' => array('value' => number_format($totals['wt']/1000,3).'ms', 'delta'=>''),
            'cpu time' => array('value' => number_format($totals['cpu']/1000,3).'ms', 'delta'=>''),
            'memory' => array('value' => number_format($totals['mu']/1024,3).'k', 'delta'=>''),
        );

        if($last) {
            $row['calls']['delta'] = format_delta($totals['ct'], $last[$method]['totals']['ct'], true);
            $row['wall time']['delta'] = format_delta($totals['wt'], $last[$method]['totals']['wt'], true);
            $row['cpu time']['delta'] = format_delta($totals['cpu'], $last[$method]['totals']['cpu'], true);
            $row['memory']['delta'] = format_delta($totals['mu'], $last[$method]['totals']['mu'], true);
        }

        printf("%-20s | run %-15s", $method, $data['runId']);
        print_table_row($row);
    }
}

function print_table_row($row)
{
    foreach($row as $cell=>$data) {
        printf(" | %s%s%10s", $cell, pad_ansi_text($data['delta'], 10), $data['value']);
    }

    printf("\n");
}

function pad_ansi_text($text, $width)
{
    $visibleText = preg_replace("/\033\[\d+m(.+?)\033\[37m/",'\1', $text);
    $spaces = ($width-strlen($visibleText) > 0) ? str_repeat(' ', $width-strlen($visibleText)) : '';

    return $spaces.$text;
}

function format_delta($num1, $num2, $positive=true, $pad=5)
{
    $delta = ($num1-$num2)/$num2;
    $format = '%4s';

    if($delta > 0) {
        $sign = '+';
    } else if($delta < 0) {
        $sign = '-';
    }

    if(($positive && $sign == '+') || (!$positive && $sign == '-')) {
        $format = "\033[31m{$format}\033[37m";
    } else {
        $format = "\033[32m{$format}\033[37m";
    }

    return pad_ansi_text(sprintf($format,$sign.number_format(abs($delta*100),1).'%'), $pad);
}

function benchmark($file, $dir)
{
    $result = `php bin/bench.php $file $dir --json`;
    return json_decode($result, true);
}

// ---------------------------------------
// main

require_once(__DIR__.'/../vendor/autoload.php');

// there is a notice thrown in the FB xhprof lib :(
error_reporting(E_ALL ^ E_NOTICE);

$testfile = $argv[1];
$dir = $argv[2];
$revspec = $argv[3];
$baseline = array();

printf("\nTesting %s (%s)\n", $revspec, get_commit_log($dir, $revspec));
$tmpdir = get_working_dir($dir, $revspec);
$results = benchmark($testfile, $tmpdir);
print_results($results['results']);
$baseline = $results;

printf("\nTesting working dir (%s)\n", $dir);
$results = benchmark($testfile, $dir);
print_results($results['results'], $baseline['results']);

printf("\nCompare results at http://localhost:8000/?run1=%s&run2=%s&source=regreph_rollup\n",
    $baseline['rollUp'], $results['rollUp']);

$diskRuns = new \XHProfRuns_Default();

$totals1 = array();
$totals2 = array();
$display_calls = true;

$run1 = xhprof_compute_flat_info($diskRuns->get_run($results['rollUp'], 'regreph_rollup', $desc1), $totals1);
$run2 = xhprof_compute_flat_info($diskRuns->get_run($baseline['rollUp'], 'regreph_rollup', $desc2), $totals2);

$row = array(
    'calls' => array(
        'value' => number_format(($totals1['ct']-$totals2['ct'])),
        'delta' => format_delta($totals1['ct'], $totals2['ct'], true),
    ),
    'wall time' => array(
        'value' => number_format(($totals1['wt']-$totals2['wt'])/1000,3).'ms',
        'delta' => format_delta($totals1['wt'], $totals2['wt'], true),
    ),
    'cpu time' => array(
        'value' => number_format(($totals1['cpu']-$totals2['cpu'])/1000,3).'ms',
        'delta' => format_delta($totals1['cpu'], $totals2['cpu'], true),
    ),
    'memory' => array(
        'value' => number_format(($totals1['mu']-$totals2['mu'])/1024,3).'k',
        'delta' => format_delta($totals1['mu'], $totals2['mu'], true),
    ),
);

printf("%-42s", "Summary");
print_table_row($row);

// handle exit status

$exit = 0;

if(($totals1['cpu']-$totals2['cpu']) > 0.05) {
    printf("\n\033[31mFAIL!\033[37m CPU TIME regression\n");
    $exit = 1;
} else if (($totals1['mu']-$totals2['mu']) > 0.05) {
    printf("\n\033[31mFAIL!\033[37m MEMORY regression\n");
    $exit = 2;
}

exit($exit);

