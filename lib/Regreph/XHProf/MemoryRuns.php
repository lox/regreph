<?php

namespace Regreph\XHProf;

class MemoryRuns implements \iXHProfRuns
{
    private $_runs = array();

    /**
     * Returns XHProf data given a run id ($run) of a given
     * type ($type).
     *
     * Also, a brief description of the run is returned via the
     * $run_desc out parameter.
     */
    public function get_run($run_id, $type, &$run_desc)
    {
        return $this->_runs[$run_id];
    }

    /**
     * Save XHProf data for a profiler run of specified type
     * ($type).
     *
     * The caller may optionally pass in run_id (which they
     * promise to be unique). If a run_id is not passed in,
     * the implementation of this method must generated a
     * unique run id for this saved XHProf run.
     *
     * Returns the run id for the saved XHProf run.
     *
     */
    public function save_run($xhprof_data, $type, $run_id = null)
    {
        $run_id = $run_id ?: 'run'.(count($this->_runs)+1);
        $this->_runs[$run_id] = $xhprof_data;
        return $run_id;
    }

    public function aggregate()
    {
        return xhprof_aggregate_runs($this,
            array_keys($this->_runs), array_fill(0, count($this->_runs), 1));
    }
}
