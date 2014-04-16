<?php

namespace Regreph\Bench;

class BenchResults
{
    private $timestamp;

    private $rollUp;

    private $results = array();

    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function getRollUp()
    {
        return $this->rollUp;
    }

    public function setRollUp($rollUp)
    {
        $this->rollUp = $rollUp;

        return $this;
    }

    public function getResults()
    {
        return $this->results;
    }

    public function addResult($method, TestResult $result)
    {
        $this->results[$method] = $result;

        return $this;
    }

    public function setResults(array $results)
    {
        $this->results = $results;

        return $this;
    }

    public function toArray()
    {
        $results = $this->getResults();

        $arr = array(
            'results' => array(),
            'rollUp' => $this->getRollUp(),
            'timestamp' => $this->getTimestamp(),
        );

        foreach ($results as $method => $testResult)
        {
            $arr['results'][$method] = array(
                'runId' => $testResult->runId,
                'totals' => $testResult->totals,
            );
        }

        return $arr;
    }
}
