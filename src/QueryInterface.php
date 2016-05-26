<?php

namespace BBC\iPlayerRadio\WebserviceKit;

/**
 * Interface QueryInterface
 *
 * If you want to write a totally custom query structure for the webservice you're calling
 * you can simply implement this interface and pass it into Service::fetch();
 *
 * @package     BBC\iPlayerRadio\WebserviceKit
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 */
interface QueryInterface
{
    /**
     * Returns a friendly (and safe) name of the webservice this query hits which we can use in
     * error logging and circuit breakers etc. [a-z0-9-_] please.
     */
    public function getServiceName();

    /**
     * Returns the headers to send with any request to this service.
     *
     * @return  array
     */
    public function getRequestHeaders();

    /**
     * Gives services a hook to modify the request options being sent with a request.
     *
     * @param   array   $options
     * @return  array
     */
    public function overrideRequestOptions(array $options);

    /**
     * Returns the URL to query, with any parameters applied.
     *
     * @return  string
     */
    public function getURL();

    /**
     * Returns the cache key to use for this query
     *
     * @return  string
     */
    public function getCacheKey();

    /**
     * Returns the slow threshold for this service, in milliseconds!
     *
     * @return  int
     */
    public function getSlowThreshold();

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
    public function getShortTimeouts();

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
    public function getLongTimeouts();

    /**
     * Return the maximum age to keep the response cached. Note; Service will ignore this
     * value if the response has a max-age portion of the Cache-Control header
     *
     * @return  int
     */
    public function getMaxAge();

    /**
     * Return the age at which to consider a cached response stale. Note; WebserviceKit\Service will
     * ignore this value if the response has a stale-while-revalidate portion of it's Cache-Control header.
     *
     * @return  int
     */
    public function getStaleAge();

    /**
     * Returns whether or not this query can be cached or not. For personalised information, this is usually
     * a no.
     *
     * @return  bool
     */
    public function canCache();

    /**
     * Returns a parameter for this query, and if not found, will return the default
     * provided in the second argument.
     *
     * @param   string  $name       Name of the param
     * @param   mixed   $default    Default value to return if param is not set
     * @return  mixed
     */
    public function getParameter($name, $default = null);

    /**
     * Given a response from the webservice, this function is called to transform it into something
     * a bit more useful for output.
     *
     * This may be passed a false or a null if the call to the webservice fails, so unit test appropriately.
     *
     * @param   mixed   $response
     * @return  mixed
     */
    public function transformPayload($response);

    /**
     * Given an Exception, return whether this should be considered a "failed" response and the breaker and monitoring
     * informed of it. Useful for when you don't want to trigger alarms because of 404s etc that Guzzle considers
     * to be Exceptions.
     *
     * @param   \Exception  $e
     * @return  bool
     */
    public function isFailureState(\Exception $e);
}
