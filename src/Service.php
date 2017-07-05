<?php

namespace BBC\iPlayerRadio\WebserviceKit;

use BBC\iPlayerRadio\Cache\Cache;
use BBC\iPlayerRadio\Cache\CacheItemInterface;
use BBC\iPlayerRadio\WebserviceKit\DataCollector\GuzzleDataCollector;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
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
     * Fetches queries from the service and hands off to the transformPayload function to do something useful
     * with it. You can pass a single query, or multiple queries in an array.
     *
     * @param           QueryInterface|QueryInterface[]  $query
     * @param           bool            $raw        Whether to ignore transformPayload or not.
     * @return          mixed
     * @throws          NoResponseException     When the cache is empty and the request fails.
     * @uses            self::multiFetch()
     */
    public function fetch($query, $raw = false)
    {
        $singleResult = !is_array($query);
        $queries = (is_array($query))? $query : [$query];

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
                        $this->monitor->onTransferStats($query, $stats);
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
                            // Check if this is considered an error:
                            if ($query->isFailureState($e)) {
                                if (isset($this->monitor)) {
                                    $this->monitor->onException($query, $e);
                                }

                                // Mark the breaker:
                                if ($breaker) {
                                    $breaker->failure();
                                }

                                // If this is an out-of-bounds exception, you aren't mocking your unit tests correctly
                                // and so we're going to yell about it.
                                if ($e instanceof \OutOfBoundsException) {
                                    throw $e;
                                }
                            } elseif ($e instanceof ClientException) {
                                // We treat this as a normal response:
                                $results[$idx] = $this->getCacheObject($e->getResponse());
                                $this->cacheQueryResponse($cacheItem, $query, $e->getResponse(), $results[$idx]);

                                if ($breaker) {
                                    $breaker->success();
                                }
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

        return ($singleResult)? $transformed[0] : $transformed;
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
        $headers = $response->getHeaders() === null ? [] : $response->getHeaders();
        $headers['statusCode'] = $response->getStatusCode();

        return [
            'body' => (string)$response->getBody(),
            'headers' => $headers
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
            // Work out the lifetimes:
            $ccHeader = ($response instanceof ResponseInterface)?
                $response->getHeader('Cache-Control')
                : null
            ;
            list($staleAge, $maxAge) = $query->getCacheAges($ccHeader);

            if ($maxAge !== false) {
                // Now we can finally cache the thing:
                $cacheItem->setData($cacheObject);
                $cacheItem->setBestBefore($staleAge);
                $cacheItem->setLifetime($maxAge);

                $this->cache->save($cacheItem);
            }
        }
    }
}
