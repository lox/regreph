<?php

namespace Regreph\TestCase;

class TestCaseFinder
{
    public static function fromFile($file)
    {
        $before = get_declared_classes();
        require_once($file);
        $after = get_declared_classes();
        $loaded = array_diff($after, $before);

        // find any performance tests
        $tests = array_filter($loaded, function($className) {
            return is_subclass_of($className, '\Regreph\TestCase\TestCase', true);
        });

        foreach($tests as $idx => $className) {
            $tests[$idx] = new $className();
        }

        return $tests;
    }
}
