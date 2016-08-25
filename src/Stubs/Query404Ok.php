<?php

namespace BBC\iPlayerRadio\WebserviceKit\Stubs;

use GuzzleHttp\Exception\ClientException;

class Query404Ok extends Query
{
    /**
     * Given an Exception, return whether this should be considered a "failed" response and the breaker and monitoring
     * informed of it. Useful for when you don't want to trigger alarms because of 404s etc that Guzzle considers
     * to be Exceptions.
     *
     * @param   \Exception $e
     * @return  bool
     */
    public function isFailureState(\Exception $e)
    {
        if ($e instanceof ClientException && $e->getResponse()->getStatusCode() === 404) {
            return false;
        }
        return true;
    }
}
