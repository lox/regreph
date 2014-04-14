<?php

namespace Regreph;

class TestResult
{
  public
    $result,
    $totals,
    $runId
    ;

  public function __construct($result, $totals, $runId)
  {
    $this->result = $result;
    $this->totals = $totals;
    $this->runId = $runId;
  }
}

