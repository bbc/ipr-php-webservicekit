<?php

namespace BBC\iPlayerRadio\WebserviceKit\Stubs;

use BBC\iPlayerRadio\WebserviceKit\MonitoringInterface;
use BBC\iPlayerRadio\WebserviceKit\QueryInterface;

class Monitoring implements MonitoringInterface
{
    protected $apisCalled;
    protected $slowResponses = [];
    protected $responseTimes = [];
    protected $exceptions = [];

    public function apisCalled(array $callCounts)
    {
        $this->apisCalled = $callCounts;
        return $this;
    }

    public function getApisCalled()
    {
        return $this->apisCalled;
    }

    public function slowResponse($serviceName, $url, $time)
    {
        $this->slowResponses[] = [
            'service' => $serviceName,
            'url' => $url,
            'time' => $time,
        ];
        return $this;
    }

    public function getSlowResponses()
    {
        return $this->slowResponses;
    }

    public function responseTime($serviceName, $url, $time)
    {
        $this->responseTimes[] = [
            'service' => $serviceName,
            'url' => $url,
            'time' => $time,
        ];
        return $this;
    }

    public function getResponseTimes()
    {
        return $this->responseTimes;
    }

    public function onException(QueryInterface $query, \Exception $e)
    {
        $this->exceptions[] = [
            'query' => $query,
            'exception' => $e,
        ];
    }

    public function getExceptions()
    {
        return $this->exceptions;
    }
}
