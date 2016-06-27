<?php

namespace BBC\iPlayerRadio\WebserviceKit\PHPUnit;

use BBC\iPlayerRadio\Cache\Cache;
use BBC\iPlayerRadio\WebserviceKit\Service;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Monitoring;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Query;
use Doctrine\Common\Cache\ArrayCache;
use GuzzleHttp\Client;
use Solution10\CircuitBreaker\CircuitBreaker;

trait GetMockedService
{
    use GetMockedGuzzleClient;

    /**
     * @var     Client
     */
    protected $client;

    /**
     * @var     Cache
     */
    protected $cache;

    /**
     * @var     Monitoring
     */
    protected $monitor;

    /**
     * @var     CircuitBreaker
     */
    protected $circuitBreaker;

    /**
     * Returns a phpUnit mocked service instance.
     *
     * @param   array       $responses      Responses to be passed to getMockedGuzzleClient
     * @return  Service
     */
    protected function getMockedService(array $responses = [])
    {
        $this->client = $this->getMockedGuzzleClient($responses);
        $this->cache = new Cache(new ArrayCache());
        $this->monitor = new Monitoring();
        $this->circuitBreaker = new CircuitBreaker('webservicekit_tests', new ArrayCache());
        return new Service($this->client, $this->cache, $this->monitor, $this->circuitBreaker);
    }

    /**
     * Returns a phpUnit mocked Query object.
     *
     * @param   string  $url    Mock url endpoint (customisable for multiFetch testing)
     * @return  Query
     */
    protected function getMockedQuery($url = 'webservicekit')
    {
        return new Query($url);
    }
}
