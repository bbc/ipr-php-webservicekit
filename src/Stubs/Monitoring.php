<?php

namespace BBC\iPlayerRadio\WebserviceKit\Stubs;

use BBC\iPlayerRadio\WebserviceKit\MonitoringInterface;
use BBC\iPlayerRadio\WebserviceKit\QueryInterface;
use GuzzleHttp\TransferStats;

class Monitoring implements MonitoringInterface
{
    protected $apisCalled;
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

    public function onTransferStats(QueryInterface $query, TransferStats $stats)
    {
        $this->responseTimes[] = [
            'service' => $query->getServiceName(),
            'url' => $query->getURL(),
            'time' => $stats->getTransferTime(),
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
