<?php

namespace Regreph\Bench;

class TestResult
{
    public $result;

    public $totals;

    public $runId;

    public function __construct($result, $totals, $runId)
    {
        $this->result = $result;
        $this->totals = $totals;
        $this->runId = $runId;
    }
}
