<?php

namespace BBC\iPlayerRadio\WebserviceKit\Stubs;

class ProgrammesQuery extends Query
{
    public function setPid($pid)
    {
        return $this->setParameter('pid', $pid);
    }

    /**
     * Returns the URL to query, with any parameters applied.
     *
     * @return  string
     */
    public function getURL()
    {
        return 'http://example.com/programmes.json?'.http_build_query($this->params);
    }

    /**
     * Returns a friendly (and safe) name of the webservice this query hits which we can use in
     * error logging and circuit breakers etc. [a-z0-9-_] please.
     */
    public function getServiceName()
    {
        return 'mock-programmes';
    }
}
