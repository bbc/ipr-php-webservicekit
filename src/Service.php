<?php

namespace BBC\iPlayerRadio\WebserviceKit;

use BBC\iPlayerRadio\Cache\Cache;
use BBC\iPlayerRadio\Cache\CacheItemInterface;
use BBC\iPlayerRadio\WebserviceKit\DataCollector\GuzzleDataCollector;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\TransferStats;
use Solution10\CircuitBreaker\CircuitBreakerInterface;

/**
 * Class Service
 *
 * This class forms the basis of our model layer and provides a resilient, fast and cache-aware service fetcher.
 *
 * Features:
 *  - multi-async requests
 *  - stale-while-revalidate caching
 *  - monitoring + logging
 *  - adaptive timeouts
 *
 * @package     BBC\iPlayerRadio\WebserviceKit
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 * @see         docs/04-webservicekit.md
 */
class Service implements ServiceInterface
{
    /**
     * @var     Client
     */
    protected $http;

    /**
     * @var     Cache
     */
    protected $cache;

    /**
     * @var     MonitoringInterface
     */
    protected $monitor;

    /**
     * @var     CircuitBreakerInterface
     */
    protected $breaker;

    /**
     * Pass in a Guzzle client and the Cache instance
     *
     * @param   Client                      $client
     * @param   Cache                       $cache
     * @param   MonitoringInterface         $monitor    Monitoring handler
     * @param   CircuitBreakerInterface     $breaker    Circuit Breaker
     */
    public function __construct(
        Client $client,
        Cache $cache,
        MonitoringInterface $monitor,
        CircuitBreakerInterface $breaker
    ) {
        $this->http = $client;
        $this->cache = $cache;
        $this->monitor = $monitor;
        $this->breaker = $breaker;
    }

    /**
     * Returns the Guzzle client that this service is using
     *
     * @return  Client
     */
    public function getClient()
    {
        return $this->http;
    }

    /**
     * Sets the client for this service to use.
     *
     * @param   Client  $client
     * @return  $this
     */
    public function setClient(Client $client)
    {
        $this->http = $client;
        return $this;
    }

    /**
     * Sets the circuit breaker to use for this webservice. If you don't call this,
     * getServiceBreaker() will create one for you.
     *
     * @param   CircuitBreakerInterface     $breaker
     * @return  $this
     */
    public function setCircuitBreaker(CircuitBreakerInterface $breaker)
    {
        $this->breaker = $breaker;
        return $this;
    }

    /**
     * Returns the circuit breaker for this webservice.
     *
     * @return  CircuitBreakerInterface
     */
    public function getCircuitBreaker()
    {
        return $this->breaker;
    }

    /**
     * Sets the cache to use for this service.
     *
     * @param   Cache   $cache
     * @return  $this
     */
    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Returns the cache for this service
     *
     * @return  Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Sets the monitoring delegate
     *
     * @param   MonitoringInterface     $monitor
     * @return  $this
     */
    public function setMonitoring(MonitoringInterface $monitor)
    {
        $this->monitor = $monitor;
        return $this;
    }

    /**
     * Returns the monitoring delegate
     *
     * @return  MonitoringInterface
     */
    public function getMonitoring()
    {
        return $this->monitor;
    }

    /**
     * Fetches the service from the URL and hands off to the transformPayload function to do something useful
     * with it. Under the hood, this just uses multiFetch to reduce duplication.
     *
     * @param           QueryInterface  $query
     * @param           bool            $raw        Whether to ignore transformPayload or not.
     * @return          mixed
     * @throws          NoResponseException     When the cache is empty and the request fails.
     * @uses            self::multiFetch()
     */
    public function fetch(QueryInterface $query, $raw = false)
    {
        return $this->multiFetch([$query], $raw)[0];
    }

    /**
     * Fetches multiple queries from the webservice simultaneously using multi-curl or similar.
     * Same deal with stale-while-revalidate etc as fetch(), and responses are returned in the
     * same order as the queries were passed in.
     *
     * @param   QueryInterface[]    $queries
     * @param   bool                $raw
     * @return  array               Array of transformPayload objects
     */
    public function multiFetch(array $queries, $raw = false)
    {
        $results            = [];
        $requests           = [];
        $serviceNameCounts  = [];

        // Firstly, let's loop through and fetch any cached responses:
        foreach ($queries as $idx => $query) {
            $url                = $query->getURL();
            $cacheKey           = md5($url);
            $cacheItem          = $this->cache->get($cacheKey);
            $results[$idx]      = $cacheItem->getData();
            $breaker            = $this->getCircuitBreaker();

            if ($breaker->isClosed() && ($cacheItem->isExpired() || $cacheItem->isStale())) {
                $timeouts = $this->getTimeouts($query, $cacheItem);
                $options = [
                    'connect_timeout'   => $timeouts['connect_timeout'],
                    'timeout'           => $timeouts['timeout'],
                    'on_stats'          => function (TransferStats $stats) use ($query) {
                        $transferTimeMS = $stats->getTransferTime() * 1000;
                        $this->monitorResponseTime($query, $transferTimeMS);
                        GuzzleDataCollector::instance()->addRequest($stats);
                    },
                    'headers'           => $query->getRequestHeaders()
                ];

                // Give the service a chance to change things:
                $options = $query->overrideRequestOptions($options);
                $requests[] = $this->http->requestAsync('GET', $url, $options)
                ->then(
                    function (ResponseInterface $response) use ($breaker, $cacheItem, $query, &$results, $raw, $idx) {
                        $this->cacheQueryResponse($cacheItem, $query, $response);
                        $breaker->success();

                        $body = (string)$response->getBody();
                        $results[$idx] = $body;
                    },
                    function (\Exception $e) use ($breaker, $cacheItem, $query, $timeouts) {
                        // Ask the query if this exception is considered a failure or not.
                        if ($query->isFailureState($e)) {
                            $this->monitor->onException($query, $e);
                            $breaker->failure();
                        }

                        // Make sure we track the time of timeouts:
                        if ($e instanceof ConnectException) {
                            $this->monitorResponseTime($query, $timeouts['timeout']*1000);
                        }
                    }
                );

                // Increment the count of this service
                $serviceNameCounts[$query->getServiceName()] =
                    (array_key_exists($query->getServiceName(), $serviceNameCounts))?
                        $serviceNameCounts[$query->getServiceName()] + 1 : 1;
            }
        }

        // Monitor the number of requests:
        $this->monitor->apisCalled($serviceNameCounts);

        // Wait until all the requests have finished.
        \GuzzleHttp\Promise\unwrap($requests);

        ksort($results);

        // Now transform their payloads:
        $transformed = [];
        foreach ($results as $i => $res) {
            $transformed[] = ($raw)? $res : $queries[$i]->transformPayload($res);
        }

        return $transformed;
    }

    /**
     * Takes a RequestInterface and correctly monitors the response time from it.
     * Used by both successful and unsuccessful responses.
     *
     * @param   QueryInterface      $query
     * @param   int                 $totalTime
     */
    public function monitorResponseTime(QueryInterface $query, $totalTime)
    {
        // Check for slowness
        $slowTime = $query->getSlowThreshold();
        if ($totalTime >= $slowTime) {
            $this->monitor->slowResponse($query->getServiceName(), $query->getURL(), $totalTime);
        }

        // Log the time itself.
        $this->monitor->responseTime($query->getServiceName(), $query->getURL(), $totalTime);
    }

    /* ------------------ Protected Helpers -------------------------- */

    /**
     * Returns the correct timeouts for a given cache item.
     *
     *  Stale:      short timeouts
     *  Expired:    long timeouts
     *
     * @param   QueryInterface      $query
     * @param   CacheItemInterface  $cacheItem
     * @return  array   ['connect_timeout' => float, 'timeout' => float]
     */
    protected function getTimeouts(QueryInterface $query, CacheItemInterface $cacheItem)
    {
        return ($cacheItem->isStale() && !$cacheItem->isExpired())?
            $query->getShortTimeouts() : $query->getLongTimeouts();
    }

    /**
     * Correctly caches the response from a query
     *
     * @param   CacheItemInterface      $cacheItem
     * @param   QueryInterface          $query
     * @param   ResponseInterface       $response
     * @return  void
     */
    protected function cacheQueryResponse(
        CacheItemInterface $cacheItem,
        QueryInterface $query,
        ResponseInterface $response
    ) {
        if ($query->canCache()) {
            // Work out the lifetime:
            $maxAge = $query->getMaxAge();
            $staleAge = $query->getStaleAge();

            if ($response instanceof ResponseInterface) {
                // Check for cache control headers:
                $parsed = \GuzzleHttp\Psr7\parse_header($response->getHeader('Cache-Control'));
                $control = [];
                foreach ($parsed as $pair) {
                    $control = array_merge($control, $pair);
                }
                $maxAge = (array_key_exists('max-age', $control))? $control['max-age'] : $query->getMaxAge();
                $staleAge = (array_key_exists('stale-while-revalidate', $control))?
                    $control['stale-while-revalidate'] : $query->getStaleAge();
            }

            // Now we can finally cache the thing:
            $cacheItem->setData((string)$response->getBody());
            $cacheItem->setBestBefore($staleAge);
            $cacheItem->setLifetime($maxAge);

            $this->cache->save($cacheItem);
        }
    }
}
