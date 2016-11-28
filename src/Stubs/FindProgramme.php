<?php

namespace BBC\iPlayerRadio\WebserviceKit\Stubs;

use BBC\iPlayerRadio\WebserviceKit\NamedQueryInterface;

class FindProgramme implements NamedQueryInterface
{
    protected $pid;

    public function __construct($pid)
    {
        $this->pid = $pid;
    }

    /**
     * Returns the query to execute against a WebserviceKit\Service instance.
     *
     * @return  \BBC\iPlayerRadio\WebserviceKit\QueryInterface
     */
    public function query()
    {
        return (new ProgrammesQuery())
            ->setPid($this->pid);
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
