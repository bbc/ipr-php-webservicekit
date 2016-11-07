<?php

namespace BBC\iPlayerRadio\WebserviceKit;

use BBC\iPlayerRadio\Cache\Cache;
use BBC\iPlayerRadio\Cache\CacheItemInterface;
use BBC\iPlayerRadio\WebserviceKit\DataCollector\GuzzleDataCollector;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
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
     * @var     array
     */
    protected $beforeHandlers = [];

    /**
     * Pass in a Guzzle client and the Cache instance
     *
     * @param   Client                      $client
     * @param   Cache                       $cache
     * @param   MonitoringInterface         $monitor    Monitoring handler
     */
    public function __construct(
        Client $client,
        Cache $cache,
        MonitoringInterface $monitor = null
    ) {
        $this->http = $client;
        $this->cache = $cache;
        $this->monitor = $monitor;
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
     * Registers a before handler onto the service. This allows you to make changes to the query object
     * just before it's about to make a request (for example setting environment correctly, or passing in
     * common config).
     *
     * You should type-hint against the type of query you want to modify to target your handlers.
     *
     * @param   callable    $callback
     * @return  $this
     */
    public function beforeQuery(callable $callback)
    {
        $this->beforeHandlers[] = $callback;
        return $this;
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
            // Call the before, if there is one:
            $query = $this->doBeforeQuery($query);

            $url                = $query->getURL();
            $cacheKey           = $query->getCacheKey();
            $cacheItem          = $this->cache->get($cacheKey);
            $results[$idx]      = $cacheItem->getData()?: ['body' => false, 'headers' => []];
            $breaker            = $query->getCircuitBreaker();

            if ((!$breaker || $breaker->isClosed()) && ($cacheItem->isExpired() || $cacheItem->isStale())) {
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
                    function (ResponseInterface $response) use ($breaker, $cacheItem, $query, &$results, $idx) {
                        $results[$idx] = $this->getCacheObject($response);
                        $this->cacheQueryResponse($cacheItem, $query, $response, $results[$idx]);

                        if ($breaker) {
                            $breaker->success();
                        }
                    },
                    function (\Exception $e) use ($breaker, $cacheItem, $query, $timeouts, &$results, $idx) {
                        if (isset($this->monitor)) {
                            $this->monitor->onException($query, $e);
                        }

                        // Cache the response if it's not a proper error:
                        if (!$query->isFailureState($e) && $e instanceof ClientException) {
                            $this->cacheQueryResponse($cacheItem, $query, $e->getResponse(), $results[$idx]);
                        }

                        // Ask the query if this exception is considered a failure or not.
                        if ($query->isFailureState($e) && $breaker) {
                            $breaker->failure();
                        }

                        // Make sure we track the time of timeouts:
                        if ($e instanceof ConnectException) {
                            $this->monitorResponseTime($query, $timeouts['timeout']*1000);
                        }

                        // If this is an out-of-bounds exception, you aren't mocking your unit tests correctly
                        // and so we're going to yell about it.
                        if ($e instanceof \OutOfBoundsException) {
                            throw $e;
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
        if (isset($this->monitor)) {
            $this->monitor->apisCalled($serviceNameCounts);
        }

        // Wait until all the requests have finished.
        \GuzzleHttp\Promise\unwrap($requests);

        ksort($results);

        // Now transform their payloads:
        $transformed = [];
        foreach ($results as $i => $res) {
            $transformed[] = ($raw)? $res['body'] : $queries[$i]->transformPayload($res['body'], $res['headers']);
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
        if ($totalTime >= $slowTime && isset($this->monitor)) {
            $this->monitor->slowResponse($query->getServiceName(), $query->getURL(), $totalTime);
        }

        // Log the time itself.
        if (isset($this->monitor)) {
            $this->monitor->responseTime($query->getServiceName(), $query->getURL(), $totalTime);
        }
    }

    /* ------------------ Protected Helpers -------------------------- */

    /**
     * Performs the beforeHandlers for a given query.
     *
     * @param   QueryInterface  $query
     * @return  QueryInterface
     */
    protected function doBeforeQuery(QueryInterface $query)
    {
        foreach ($this->beforeHandlers as $handler) {
            $reflected = new \ReflectionFunction($handler);
            $parameters = $reflected->getParameters();

            if (!$parameters) {
                throw new \LogicException('beforeHandlers must have at least one parameter!');
            }

            $parameterClass = $parameters[0]->getClass();
            if (!$parameterClass || ($parameterClass && $parameterClass->isInstance($query))) {
                $query = call_user_func_array($handler, [$query]);
            }
        }
        return $query;
    }

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
     * Builds up the object we're going to cache from a given reponse
     *
     * @param   ResponseInterface   $response
     * @return  array               ['body' => string, 'headers' => array]
     */
    protected function getCacheObject(ResponseInterface $response)
    {
        return [
            'body' => (string)$response->getBody(),
            'headers' => $response->getHeaders() === null ? [] : $response->getHeaders()
        ];
    }

    /**
     * Correctly caches the response from a query
     *
     * @param   CacheItemInterface      $cacheItem
     * @param   QueryInterface          $query
     * @param   ResponseInterface       $response
     * @param   array                   $cacheObject
     * @return  void
     */
    protected function cacheQueryResponse(
        CacheItemInterface $cacheItem,
        QueryInterface $query,
        ResponseInterface $response,
        array $cacheObject
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
            $cacheItem->setData($cacheObject);
            $cacheItem->setBestBefore($staleAge);
            $cacheItem->setLifetime($maxAge);

            $this->cache->save($cacheItem);
        }
    }
}
