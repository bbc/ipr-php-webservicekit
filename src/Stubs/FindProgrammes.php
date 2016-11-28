<?php

namespace BBC\iPlayerRadio\WebserviceKit\Stubs;

use BBC\iPlayerRadio\WebserviceKit\NamedQueryInterface;

class FindProgrammes implements NamedQueryInterface
{
    protected $pids = [];

    public function __construct(array $pids)
    {
        $this->pids = $pids;
    }

    /**
     * Returns the query to execute against a WebserviceKit\Service instance.
     *
     * @return  \BBC\iPlayerRadio\WebserviceKit\QueryInterface
     */
    public function query()
    {
        $queries = [];
        foreach ($this->pids as $pid) {
            $queries[] = (new ProgrammesQuery())->setPid($pid);
        }
        return $queries;
    }

    /**
     * Allows the NamedQuery to perform any additional processing on the result
     * before returning it.
     *
     * @param   mixed $results
     * @return  mixed
     */
    public function processResults($results)
    {
        return $results;
    }
}
