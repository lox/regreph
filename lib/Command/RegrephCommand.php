<?php

namespace Regreph\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\TableHelper;
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

        $table = $this->getHelperSet()->get('table');

        $table
            ->setPadType(STR_PAD_LEFT)
            ->setHeaders(array(
                'Profile',
                'Run ID',
                'Calls count',
                'Wall time',
                'CPU time',
                'Memory'
            ));

        $this->addRows($table, $results);

        $baseline = $results;

        printf("\nTesting working dir (%s)\n", $dir);
        $results = $this->benchmark($testfile, $dir);
        $this->print_results($results, $baseline);
        $this->addRows($table, $results, $baseline);

        $output->writeln(sprintf(
            "Compare results at http://localhost:8000/?run1=%s&run2=%s&source=".Bench::ROLLUP_KEY,
            $baseline->getRollUp(),
            $results->getRollUp()
        ));

        $diskRuns = new XHProfRuns_Default();

        $totals1 = array();
        $totals2 = array();

        xhprof_compute_flat_info($diskRuns->get_run($results->getRollUp(), Bench::ROLLUP_KEY, $desc1), $totals1);
        xhprof_compute_flat_info($diskRuns->get_run($baseline->getRollUp(), Bench::ROLLUP_KEY, $desc2), $totals2);

        $table
            ->addRow(array(
                'Summary',
                '',
                $this->format_delta($totals1['ct'], $totals2['ct']).' '.str_pad(number_format(($totals1['ct'] - $totals2['ct'])), 5, ' ', STR_PAD_LEFT),
                $this->format_delta($totals1['wt'], $totals2['wt']).' '.str_pad(number_format(($totals1['wt'] - $totals2['wt'])).'ms', 5, ' ', STR_PAD_LEFT),
                $this->format_delta($totals1['cpu'], $totals2['cpu']).' '.str_pad(number_format(($totals1['cpu'] - $totals2['cpu'])).'ms', 5, ' ', STR_PAD_LEFT),
                $this->format_delta($totals1['mu'], $totals2['mu']).' '.str_pad(number_format(($totals1['mu'] - $totals2['mu'])).'k', 5, ' ', STR_PAD_LEFT),
            ));

        $table->render($output);

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

    private function addRows(
        TableHelper $table,
        BenchResults $results,
        BenchResults $lastResults = null
    ) {
        foreach ($results->getResults() as $method => $data) {

            $totals = $data->totals;

            $callsCount = str_pad(number_format($totals['ct']), 5, ' ', STR_PAD_LEFT);
            $wallTime   = str_pad(number_format($totals['wt'] / 1000, 3).'ms', 5, ' ', STR_PAD_LEFT);
            $cpuTime    = str_pad(number_format($totals['cpu'] / 1000, 3).'ms', 5, ' ', STR_PAD_LEFT);
            $memory     = str_pad(number_format($totals['mu'] / 1024, 3).'k', 5, ' ', STR_PAD_LEFT);

            if ($lastResults) {
                $lastMethods = $lastResults->getResults();
                $lastTotals = $lastMethods[$method]->totals;

                $callsCount = $this->format_delta($totals['ct'], $lastTotals['ct']).' '.$callsCount;
                $wallTime = $this->format_delta($totals['wt'], $lastTotals['wt']).' '.$wallTime;
                $cpuTime = $this->format_delta($totals['cpu'], $lastTotals['cpu']).' '.$cpuTime;
                $memory = $this->format_delta($totals['mu'], $lastTotals['mu']).' '.$memory;
            }

            $table->addRow(array(
                $method,
                $data->runId,
                $callsCount,
                $wallTime,
                $cpuTime,
                $memory
            ));
        }
    }

    private function format_delta($num1, $num2)
    {
        if ($num2 !== 0) {
            $delta = ($num1 - $num2) / $num2;
        } elseif ($num1 === 0) {
            $delta = 0;
        } else {
            $delta = 1;
        }

        if ($delta > 0) {
            $sign = '+';
        } elseif($delta < 0) {
            $sign = '-';
        }

        $deltaString = str_pad($sign.number_format(abs($delta * 100), 1).'%', 5, ' ', STR_PAD_LEFT);

        if ($sign == '+') {
            $deltaString = "<error>{$deltaString}</error>";
        } else {
            $deltaString = "<info>{$deltaString}</info>";
        }

        return $deltaString;
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
