<?php

namespace Regreph\TestCase;
use Regreph\Bench\TestResult;
use Regreph\XHProf\MemoryRuns;
use XHProfRuns_Default;
use xhprof_compute_flat_info;

class TestCase
{
    public function setUp()
    {
    }

    public function tearDown()
    {
    }

    public function getTestMethods()
    {
        return array_filter(get_class_methods($this), function($methodName) {
            return strpos($methodName, 'test') === 0;
        });
    }

    public function run($method, $times=1)
    {
        $runs = new MemoryRuns();
        $calls = array();

        for ($i=0; $i < $times; $i++) {

            $this->setUp();
            xhprof_enable(XHPROF_FLAGS_MEMORY | XHPROF_FLAGS_CPU);

            $this->$method();

            $data = xhprof_disable();
            $runs->save_run($data, $method);

            $calls []= $this->_callCount($data);
            $this->tearDown();
        }

        $aggregated = $runs->aggregate();
        $totals = array();

        $result = xhprof_compute_flat_info($aggregated['raw'], $totals);
        $totals['ct'] = max($calls);

        // write aggregated run to disk
        $diskRuns = new XHProfRuns_Default();
        $runId = $diskRuns->save_run($aggregated['raw'], 'regreph');

        return new TestResult($result, $totals, $runId);
    }

    private function _callCount($rawData)
    {
        $total = 0;

        foreach ($rawData as $key=>$data) {
            $total += $data['ct'];
        }

        return $total;
    }
}
