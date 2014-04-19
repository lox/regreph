<?php

namespace Regreph\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Regreph\Bench\Bench;
use Regreph\Bench\BenchFile;
use Regreph\Bench\BenchResults;
use XHProfRuns_Default;
use xhprof_compute_flat_info;

class RegrephCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('regreph')
            ->setDescription('Benchmark two revisions and show regressions')
            ->addArgument(
                'testFile',
                InputArgument::REQUIRED,
                'File \Regreph\TestCase class specifying the benchmark'
            )
            ->addArgument(
                'projectDir',
                InputArgument::REQUIRED,
                'Path to the project being benchmarked.'
            )
            ->addArgument(
                'refspec',
                InputArgument::REQUIRED,
                'Revision to benchmark and compare against the current version'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // there is a notice thrown in the FB xhprof lib :(
        error_reporting(E_ALL ^ E_NOTICE);

        $testfile = $input->getArgument('testFile');
        $dir = $input->getArgument('projectDir');
        $refspec = $input->getArgument('refspec');
        $baseline = array();

        printf("\nTesting %s (%s)\n", $refspec, $this->get_commit_log($dir, $refspec));
        $tmpdir = $this->get_working_dir($dir, $refspec);
        $results = $this->benchmark($testfile, $tmpdir);
        $this->print_results($results);
        $baseline = $results;

        printf("\nTesting working dir (%s)\n", $dir);
        $results = $this->benchmark($testfile, $dir);
        $this->print_results($results, $baseline);

        printf("\nCompare results at http://localhost:8000/?run1=%s&run2=%s&source=".Bench::ROLLUP_KEY."\n",
            $baseline->getRollUp(), $results->getRollUp());

        $diskRuns = new XHProfRuns_Default();

        $totals1 = array();
        $totals2 = array();
        $display_calls = true;

        $run1 = xhprof_compute_flat_info($diskRuns->get_run($results->getRollUp(), Bench::ROLLUP_KEY, $desc1), $totals1);
        $run2 = xhprof_compute_flat_info($diskRuns->get_run($baseline->getRollUp(), Bench::ROLLUP_KEY, $desc2), $totals2);

        $row = array(
            'calls' => array(
                'value' => number_format(($totals1['ct'] - $totals2['ct'])),
                'delta' => $this->format_delta($totals1['ct'], $totals2['ct'], true),
            ),
            'wall time' => array(
                'value' => number_format(($totals1['wt'] - $totals2['wt']) / 1000, 3).'ms',
                'delta' => $this->format_delta($totals1['wt'], $totals2['wt'], true),
            ),
            'cpu time' => array(
                'value' => number_format(($totals1['cpu'] - $totals2['cpu']) / 1000, 3).'ms',
                'delta' => $this->format_delta($totals1['cpu'], $totals2['cpu'], true),
            ),
            'memory' => array(
                'value' => number_format(($totals1['mu'] - $totals2['mu']) / 1024, 3).'k',
                'delta' => $this->format_delta($totals1['mu'], $totals2['mu'], true),
            ),
        );

        printf("%-42s", "Summary");
        $this->print_table_row($row);

        // handle exit status

        $exitCode = 0;

        if (($totals1['cpu'] - $totals2['cpu']) > 0.05) {
            printf("\n\033[31mFAIL!\033[37m CPU TIME regression\n");
            $exitCode = 1;
        } else if (($totals1['mu'] - $totals2['mu']) > 0.05) {
            printf("\n\033[31mFAIL!\033[37m MEMORY regression\n");
            $exitCode = 2;
        }

        exit($exitCode);
    }

    private function get_commit_log($dir, $sha1)
    {
        $cmd = trim("git -C {$dir} show {$sha1} --pretty=oneline| head -n1");
        list($sha1, $descr) = explode(' ', trim(`$cmd`), 2);

        return $descr;
    }

    private function get_working_dir($dir, $sha1)
    {
        $tmpdir = sys_get_temp_dir().'/.'.$sha1;

        // pull down recent git changes
        if(!is_dir($tmpdir)) {
            `git clone --local {$dir} {$tmpdir}`;
        } else {
            `git -C {$tmpdir} fetch; git -C {$tmpdir} reset --hard {$sha1}`;
        }

        // update composer
        `composer install --working-dir={$tmpdir}`;

        return $tmpdir;
    }

    private function print_results(
        BenchResults $results,
        BenchResults $lastResults = null
    ) {
        foreach ($results->getResults() as $method => $data) {

            $totals = $data->totals;
            $row = array(
                'calls'     => array(
                    'value' => number_format($totals['ct']),
                    'delta'=>'',
                ),
                'wall time' => array(
                    'value' => number_format($totals['wt'] / 1000, 3).'ms',
                    'delta'=>'',
                ),
                'cpu time'  => array(
                    'value' => number_format($totals['cpu'] / 1000, 3).'ms',
                    'delta'=>'',
                ),
                'memory'    => array(
                    'value' => number_format($totals['mu'] / 1024, 3).'k',
                    'delta'=>'',
                ),
            );

            if ($last) {
                $lastMethods = $last->getResults();
                $lastTotals = $lastMethods[$method]->totals;

                $row['calls']['delta'] = $this->format_delta(
                    $totals['ct'],
                    $lastTotals['ct'],
                    true
                );
                $row['wall time']['delta'] = $this->format_delta(
                    $totals['wt'],
                    $lastTotals['wt'],
                    true
                );
                $row['cpu time']['delta'] = $this->format_delta(
                    $totals['cpu'],
                    $lastTotals['cpu'],
                    true
                );
                $row['memory']['delta'] = $this->format_delta(
                    $totals['mu'],
                    $lastTotals['mu'],
                    true
                );
            }

            printf("%-20s | run %-15s", $method, $data->runId);
            $this->print_table_row($row);
        }
    }

    private function print_table_row($row)
    {
        foreach($row as $cell=>$data) {
            printf(" | %s%s%10s", $cell, $this->pad_ansi_text($data['delta'], 10), $data['value']);
        }

        printf("\n");
    }

    private function pad_ansi_text($text, $width)
    {
        $visibleText = preg_replace("/\033\[\d+m(.+?)\033\[37m/",'\1', $text);
        $spaces = ($width-strlen($visibleText) > 0) ? str_repeat(' ', $width-strlen($visibleText)) : '';

        return $spaces.$text;
    }

    private function format_delta($num1, $num2, $positive = true, $pad = 5)
    {
        $delta = ($num1 - $num2) / $num2;
        $format = '%4s';

        if ($delta > 0) {
            $sign = '+';
        } else if($delta < 0) {
            $sign = '-';
        }

        if (($positive && $sign == '+') || (!$positive && $sign == '-')) {
            $format = "\033[31m{$format}\033[37m";
        } else {
            $format = "\033[32m{$format}\033[37m";
        }

        return $this->pad_ansi_text(sprintf($format, $sign.number_format(abs($delta * 100), 1).'%'), $pad);
    }

    /**
     * Get benchmark results given a test file
     *
     * @param  string $testFile Path to a file with \Regreph\TestCase\TestCase
     * @return \Regreph\Bench\BenchResults the results from the benchmark
     */
    private function benchmark($testFile)
    {
        $benchFile = new BenchFile($testFile);

        return $benchFile->getBench()->getResults();
    }
}
