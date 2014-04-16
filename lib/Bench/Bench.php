<?php

namespace Regreph\Bench;

use XHProfRuns_Default;
use xhprof_aggregate_runs;

class Bench
{
    const ROLLUP_KEY = 'regreph_rollup';

    private $testCases;

    private $results;

    public function __construct(array $testCases)
    {
        $this->setTestCases($testCases);
    }

    public function getTestCases()
    {
        return $this->testCases;
    }

    public function setTestCases($testCases)
    {
        $this->testCases = $testCases;

        return $this;
    }

    public function getResults()
    {
        if (!$this->results) {
            $this->results = self::runTestCases($this->getTestCases());
        }

        return $this->results;
    }

    private static function runTestCases(array $testCases)
    {
        // there is a notice thrown in the FB xhprof lib :(
        error_reporting(E_ALL ^ E_NOTICE);

        $results = new BenchResults;

        $runs = array();

        foreach ($testCases as $testCase) {
            foreach ($testCase->getTestMethods() as $method) {
                $result = $testCase->run($method, 25);
                $runs []= $result->runId;
                $results->addResult($method, $result);
            }
        }

        if ($runs) {
            // rollup all test method runs into one run
            $diskRuns = new XHProfRuns_Default();
            $rollup = xhprof_aggregate_runs(
                $diskRuns,
                $runs,
                array_fill(0, count($runs), 1),
                'regreph'
            );

            $rollupId = $diskRuns->save_run($rollup['raw'], static::ROLLUP_KEY);
            $results->setRollUp($rollupId);
        }

        $results->setTimestamp(date('r'));

        return $results;
    }
}
