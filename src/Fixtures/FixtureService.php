<?php

namespace BBC\iPlayerRadio\WebserviceKit\Fixtures;

use BBC\iPlayerRadio\Cache\Cache;
use BBC\iPlayerRadio\WebserviceKit\DataCollector\FixturesDataCollector;
use BBC\iPlayerRadio\WebserviceKit\NoResponseException;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedGuzzleClient;
use BBC\iPlayerRadio\WebserviceKit\QueryInterface;
use BBC\iPlayerRadio\WebserviceKit\Service;
use BBC\iPlayerRadio\WebserviceKit\ServiceInterface;
use Doctrine\Common\Cache\ArrayCache;
use GuzzleHttp\Psr7\Response;

/**
 * Class FixtureService
 *
 * A 'wrapper' service around a real WebserviceKit service that allows you to modify the responses
 * that specific queries will return.
 *
 * instances of this class will be placed in the DI container over the top of the ones they are mocking.
 *
 * @package     BBC\iPlayerRadio\WebserviceKit\Fixtures
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 */
class FixtureService implements ServiceInterface
{
    use GetMockedGuzzleClient;

    /**
     * @var     Service
     */
    protected $originalService;

    /**
     * @var     array
     */
    protected $alteredServices = [];

    /**
     * @var     string
     */
    protected $currentService = null;

    /**
     * @var     array
     */
    protected $conditions = [];

    /**
     * @var     QueryCondition
     */
    protected $currentQuery;

    /**
     * @param   Service                 $original
     */
    public function __construct(Service $original)
    {
        $this->originalService = $original;
    }

    /**
     * @param   Service     $original
     * @return  $this
     */
    public function setOriginalService(Service $original)
    {
        $this->originalService = $original;
        return $this;
    }

    /**
     * @return  Service
     */
    public function getOriginalService()
    {
        return $this->originalService;
    }

    /**
     * Magic call lets us capture any methods called that we can now ignore.
     *
     * @param   $name       string
     * @param   $arguments  array
     * @return  mixed
     */
    public function __call($name, $arguments)
    {
        // Do nothing, just capture.
    }

    /* -------------- Defining Failure Conditions ---------------------- */

    /**
     * Get the current query or define it if it not already set
     *
     * @param   string|null         $fixtureDefinition
     * @return  QueryCondition
     */
    public function getCurrentQuery($fixtureDefinition = null)
    {
        if (!isset($this->currentQuery)) {
            $this->currentQuery = new QueryCondition($fixtureDefinition);
            $this->currentQuery->service($this->currentService);
        }
        return $this->currentQuery;
    }

    /**
     * Marks a service as altered.
     *
     * @param   string  $service
     * @param   string  $fixtureDefinition
     * @return  $this
     */
    public function alterService($service, $fixtureDefinition = null)
    {
        $this->currentService = $service;
        $this->getCurrentQuery($fixtureDefinition)->service($service);
        return $this;
    }

    /**
     * Every request to the service will do whatever willReturn says
     *
     * @return $this
     */
    public function allRequests()
    {
        $this->getCurrentQuery()->any();
        return $this;
    }

    /**
     * Match a segment of the URI against a given string
     *
     * @param $key string key to match in the uri
     * @return $this
     */
    public function uriHas($key)
    {
        $this->getCurrentQuery()->uriHas($key);
        return $this;
    }

    /**
     * If $query->getParameter($key) == $value
     *
     * @param   string              $key
     * @param   string|int|array    $value
     * @return  $this
     */
    public function queryHas($key, $value)
    {
        $this->getCurrentQuery()->has($key, $value);
        return $this;
    }

    /**
     * If $query->getParameter($key) != $value
     *
     * @param   string              $key
     * @param   string|int|array    $value
     * @return  $this
     */
    public function queryHasNot($key, $value)
    {
        $this->getCurrentQuery()->hasNot($key, $value);
        return $this;
    }

    /**
     * Defines what the service will return as part of this condition.
     *
     * @param   string|callable     $body
     * @param   int                 $statusCode
     * @param   array               $headers
     * @param   int                 $sleep
     * @return  $this
     */
    public function willReturn($body, $statusCode = 200, array $headers = [], $sleep = 0)
    {
        $this->conditions[] = [
            'cond'      => $this->currentQuery,
            'status'    => $statusCode,
            'body'      => $body,
            'headers'   => $headers,
            'sleep'     => $sleep,
        ];
        unset($this->currentQuery);
        return $this;
    }

    /**
     * Returns all of the conditions that are defined for this service.
     *
     * @return array
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /* ---------------- WebserviceKit Overrides -------------------------- */

    /**
     * This class is responsible for determining the failure condition and throwing appropriately.
     *
     * @param   QueryInterface      $query
     * @param   bool                $raw
     * @return  mixed
     * @throws  NoResponseException     When the cache is empty and the request fails.
     */
    public function fetch(QueryInterface $query, $raw = false)
    {
        // Loop through our conditions and see if one matches:
        $match = false;
        foreach ($this->conditions as $cond) {
            if ($cond['cond']->matches($query)) {
                $match = &$cond;
                break;
            }
        }

        // If we have a match, do something:
        $realCache = false;
        $realClient = false;
        if ($match && is_array($match)) {
            $response = $this->buildResponse($match, $query);
            FixturesDataCollector::instance()->conditionMatched($query, $match['cond'], $response);

            // Swap out the service's HTTP client and a fake cache for this request:
            $realClient = $this->originalService->getClient();
            $this->originalService->setClient($this->getMockedGuzzleClient([
                $response
            ]));

            $realCache = $this->originalService->getCache();
            $this->originalService->setCache(new Cache(new ArrayCache()));

            // If we've been asked to sleep, do it now:
            if ($match['sleep'] !== 0) {
                sleep($match['sleep']);
            }
        }

        // Pass through to the original service to allow Guzzle to do it's thang:
        $realResponse = null;
        try {
            $realResponse = $this->originalService->fetch($query, $raw);
        } catch (\Exception $e) {
            throw $e;
        } finally {
            if ($match) {
                $this->originalService->setClient($realClient);
                $this->originalService->setCache($realCache);
            }
        }

        return $realResponse;
    }

    /**
     * Overrides multi-fetch to also do the mocking goodness
     *
     * @param   QueryInterface[]    $queries
     * @param   bool                $raw
     * @return  array               Array of transformPayload objects
     */
    public function multiFetch(array $queries, $raw = false)
    {
        // We can just cheat and call fetch() in a loop:
        $realResponses = [];
        foreach ($queries as $query) {
            $realResponses[] = $this->fetch($query, $raw);
        }
        return $realResponses;
    }

    /**
     * Returns a Response from a given 'match', appropriately calling callbacks or constructing objects
     * as required.
     *
     * @param   array           $match
     * @param   QueryInterface  $query
     * @return  Response
     */
    protected function buildResponse(array &$match, QueryInterface $query)
    {
        $responseBody = (is_callable($match['body']))? call_user_func($match['body'], $query) : $match['body'];

        if (is_object($responseBody) && $responseBody instanceof Response) {
            return $responseBody;
        }

        $match['body'] = $responseBody;

        $response = new Response(
            $match['status'],
            $match['headers'],
            $match['body']
        );
        return $response;
    }
}
