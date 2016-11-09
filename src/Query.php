<?php

namespace BBC\iPlayerRadio\WebserviceKit;

use Solution10\CircuitBreaker\CircuitBreaker;

/**
 * Class Query
 *
 * Abstract base class for building a query for a webservice.
 *
 * @package     BBC\iPlayerRadio\WebserviceKit
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 */
abstract class Query implements QueryInterface
{
    const LOCAL = 'local';
    const INT = 'int';
    const TEST = 'test';
    const STAGE = 'stage';
    const LIVE = 'live';

    /**
     * @var     array
     */
    protected $validEnvironments = [self::LOCAL, self::INT, self::TEST, self::STAGE, self::LIVE];

    /**
     * @var     string
     */
    protected $env = self::LIVE;

    /**
     * @var     array
     */
    protected $params = [];

    /**
     * @var     CircuitBreaker
     */
    protected $breaker;

    /**
     * @var     array
     */
    protected $config = [];

    /**
     * @var integer
     */
    protected $maxAge = 300;

    /**
     * @var integer
     */
    protected $staleAge = 60;

    /**
     * Returns the headers to send with any request to this service.
     *
     * @return  array
     */
    public function getRequestHeaders()
    {
        return [];
    }

    /**
     * Gives services a hook to modify the request options being sent with a request.
     *
     * @param   array $options
     * @return  array
     */
    public function overrideRequestOptions(array $options)
    {
        return $options;
    }

    /**
     * Returns the cache key to use for this query
     *
     * @return  string
     */
    public function getCacheKey()
    {
        return md5($this->getURL());
    }

    /**
     * Returns the slow threshold for this service, in milliseconds!
     *
     * @return  int
     */
    public function getSlowThreshold()
    {
        return 3000;
    }

    /**
     * Returns the "short" connect and response timeouts for this webservice. These should be
     * the sort of performance you'd expect the target service to have when hot and firing on
     * all cylinders.
     *
     * connect_timeout is DNS and initial connection timeout
     * timeout is the timeout to response from the webservice
     *
     * Both map onto their Guzzle config counterparts:
     * http://guzzle.readthedocs.io/en/latest/request-options.html#connect-timeout
     * http://guzzle.readthedocs.io/en/latest/request-options.html#timeout
     *
     * @return  array   ['connect_timout' => int (seconds), 'timeout' => int (seconds)]
     */
    public function getShortTimeouts()
    {
        return ['connect_timeout' => 1, 'timeout' => 3];
    }

    /**
     * Returns the "long" connect and response timeouts for this webservice. These should be
     * the sort of performance you'd expect when the target webservice is totally cold, and probably half-way down.
     *
     * connect_timeout is DNS and initial connection timeout
     * timeout is the timeout to response from the webservice
     *
     * Both map onto their Guzzle config counterparts:
     * http://guzzle.readthedocs.io/en/latest/request-options.html#connect-timeout
     * http://guzzle.readthedocs.io/en/latest/request-options.html#timeout
     *
     * @return  array   ['connect_timout' => int (seconds), 'timeout' => int (seconds)]
     */
    public function getLongTimeouts()
    {
        return ['connect_timeout' => 10, 'timeout' => 10];
    }

    /**
     * Return the maximum age to keep the response cached. Note; Service will ignore this
     * value if the response has a max-age portion of the Cache-Control header
     *
     * @return  int
     */
    public function getMaxAge()
    {
        return $this->maxAge;
    }

    /**
     * Set the maximum age to keep the response cached
     * @param int $maxAge
     */
    public function setMaxAge($maxAge)
    {
        $this->maxAge = $maxAge;
        return $this;
    }

    /**
     * Return the age at which to consider a cached response stale. Note; WebserviceKit\Service will
     * ignore this value if the response has a stale-while-revalidate portion of it's Cache-Control header.
     *
     * @return  int
     */
    public function getStaleAge()
    {
        return $this->staleAge;
    }

    /**
     * Set the age at which to consider a cached response stale
     * @param int $staleAge
     */
    public function setStaleAge($staleAge)
    {
        $this->staleAge = $staleAge;
        return $this;
    }

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
        return true;
    }

    /**
     * @param   string  $env    Environment we're querying
     * @return  $this
     * @throws  \InvalidArgumentException
     */
    public function setEnvironment($env)
    {
        if (in_array($env, $this->validEnvironments)) {
            $this->env = $env;
            return $this;
        }
        throw new \InvalidArgumentException('"'.$env.'" is not a supported environment for '.get_class($this));
    }

    /**
     * Returns the environment we're querying
     *
     * @return  string
     */
    public function getEnvironment()
    {
        return $this->env;
    }

    /**
     * Sets a parameter to the query. Doesn't validate it in any way.
     *
     * @param   string  $name   Param name (ie 'lang')
     * @param   mixed   $value
     * @return  $this
     */
    public function setParameter($name, $value)
    {
        $this->params[$name] = $value;
        return $this;
    }

    /**
     * Returns a parameter for this query, and if not found, will return the default
     * provided in the second argument.
     *
     * @param   string  $name       Name of the param
     * @param   mixed   $default    Default value to return if param is not set
     * @return  mixed
     */
    public function getParameter($name, $default = null)
    {
        return (array_key_exists($name, $this->params))? $this->params[$name] : $default;
    }

    /**
     * Returns whether or not this query can be cached or not. For personalised information, this is usually
     * a no.
     *
     * @return  bool
     */
    public function canCache()
    {
        return true;
    }

    /**
     * Sets the circuit breaker to use for this query
     *
     * @param   CircuitBreaker  $breaker
     * @return  $this
     */
    public function setCircuitBreaker(CircuitBreaker $breaker)
    {
        $this->breaker = $breaker;
        return $this;
    }

    /**
     * Returns the appropriate circuitbreaker to use for this query
     *
     * @return  CircuitBreaker
     */
    public function getCircuitBreaker()
    {
        return $this->breaker;
    }

    /**
     * @return  array|null
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param   array   $config
     * @return  $this
     */
    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Returns the URL to query, with any parameters applied.
     *
     * @return  string
     */
    abstract public function getURL();

    /**
     * Allows casting to a string
     *
     * @return  string
     */
    public function __toString()
    {
        return $this->getURL();
    }
}
