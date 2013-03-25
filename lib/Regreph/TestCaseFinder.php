<?php

namespace Regreph;

class TestCaseFinder
{
    public static function fromFile($file)
    {
        $before = get_declared_classes();
        require_once($file);
        $after = get_declared_classes();
        $loaded = array_diff($after, $before);

        // find any performance tests
        $tests = array_filter($loaded, function($c) {
            return is_subclass_of($c, '\Regreph\TestCase', true);
        });

        foreach($tests as $idx=>$test) {
            $tests[$idx] = new $test();
        }

        return $tests;
    }
}
