<?php

namespace Regreph\Bench;

use Regreph\TestCase\TestCaseFinder;

class BenchFile
{
    private $file;

    private $tests;

    private $bench;

    public function __construct($file)
    {
        $this->setFile($file);
    }

    public function getFile()
    {
        return $file;
    }

    public function setFile($file)
    {
        $this->file = $file;
        $this->tests = TestCaseFinder::fromFile($this->file);
        $this->bench = $bench = new Bench($this->tests);

        return $this;
    }

    public function getTests()
    {
        return $this->tests;
    }

    public function getBench()
    {
        return $this->bench;
    }
}
