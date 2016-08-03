# Queries

Queries describe exactly how WebserviceKit should talk to your backend services and form the heart
of your integration with the library.

- [A basic example](#a-basic-example)
- [QueryInterface and Query](#queryinterface-and-query)
- [Query Parameters](#query-parameters)
- [Environments](#environments)
- [transformPayload](#transformPayload)
- [Request Headers](#request-headers)
- [Request options](#request-options)
- [Thresholds](#thresholds)
    - [getSlowThreshold()](#getslowthreshold)
    - [Adaptive Timeouts](#adaptive-timeouts)
- [Caching](#caching)
- [Circuit Breakers](#circuit-breakers)
- [isFailureState](#isFailureState)

## A basic example

Let's say we have a simple REST service that returns a list of articles encoded in JSON format:

```
URL: http://api.example.com/articles.json
Status: 200 OK
Content-Type: application/json
Cache-Control: max-age=60

{
    "articles": [
        {
            "id": 1,
            "title": "Help, I'm addicted to The Archers!"
        },
        {
            "id": 2,
            "title": "Infinite Monkey Cage: Best Radio Programme ever?"
        }
    ]
}

```

We could build a Query to it very easily like so:

```php
<?php

class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    /**
     * Returns the URL to query, with any parameters applied.
     *
     * @return  string
     */
    public function getURL() {
        return 'http://api.example.com/articles.json';
    }
    
    /**
     * Returns a friendly (and safe) name of the webservice this query hits which we can use in
     * error logging and circuit breakers etc. [a-z0-9-_] please.
     */
    public function getServiceName() {
        return 'articles-service'; 
    }
    
    /**
     * Given a response from the webservice, this function is called to transform it into something
     * a bit more useful for output.
     *
     * This may be passed a false or a null if the call to the webservice fails, so unit test appropriately.
     *
     * @param   mixed   $response
     * @param   array   $headers
     * @return  mixed
     */
    public function transformPayload($response, array $headers) {
        if ($response) {
            return json_decode($response);
        }
        return false;
    }
}

```

WebserviceKit provides a base Query class that can make writing queries super quick and easy
(more in [QueryInterface and Query](#queryinterface-and-query)). You simply need to fill in the above three
methods: `getURL()`, `getServiceName()` and `transformPayload()`. Give the docblocks above a read as to what
each of these methods does.

We'll build on this Query example in the next few examples.

### QueryInterface and Query

All Queries must implement the `BBC\iPlayerRadio\WebserviceKit\QueryInterface` interface.

However, this is quite a detailed Interface and for a lot of Queries you will be doing much the same thing.
Therefore, WebserviceKit provides an abstract base class to extend from: `BBC\iPlayerRadio\WebserviceKit\Query`.
Using this, you only need to implement `getURL()`, `getServiceName()` and `transformPayload()`, although you
can obviously override anything else you need to.

## Query Parameters

Our articles query above is pretty static. Let's assume that the endpoint above provides pagination via
`page=` and `perPage=` query parameters. We can add that into our Query class like so:

```php
class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    /**
     * Returns the URL to query, with any parameters applied.
     *
     * @return  string
     */
    public function getURL() {
        $queryString = [
            'page' => $this->getParameter('page', 1), // returns the page if set, otherwise 1
            'perPage' => $this->getParameter('perPage', 25)
        ];
        return 'http://api.example.com/articles.json?'.http_build_query($queryString);
    }
    ...
}
```

We can then assign those parameters like so:

```php
$query = (new ArticlesQuery())
    ->setParameter('page', 2)
    ->setParameter('perPage', 50);

$result = $service->fetch($query);
```

This'll work fine, but it has a problem; we're exposing the details of the API to the user of our `ArticlesQuery`
class. If the articles API changes and the pagination parameters become `limit=` and `offset=` we have to go and
change all our `setParameter()` calls. Therefore, we strongly recommend that you do this:

```php
class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    /**
     * Returns the URL to query, with any parameters applied.
     *
     * @return  string
     */
    public function getURL() {
        $queryString = [
            'page' => $this->getParameter('page', 1),
            'perPage' => $this->getParameter('perPage', 25)
        ];
        return 'http://api.example.com/articles.json?'.http_build_query($queryString);
    }
    
    public function setPage($page)
    {
        $this->setParameter('page', $page);
        return $this;
    }
    
    public function setPerPage($perPage)
    {
        $this->setParameter('perPage', $perPage);
        return $this;
    }
    
    ...
}
```

```php
$query = (new ArticlesQuery())
    ->setPage(2)
    ->setPerPage(50);
    
$result = $service->fetch($query);
```

## Environments

Your API backends will probably exist on multiple environments (int, test, stage, live for example). The abstract
base `Query` class has this concept and allows you to define valid environment names and pass them in like so:

```php
class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    /**
     * @var     array
     */
    protected $validEnvironments = [self::INT, self::TEST, self::STAGE, self::LIVE];

    /**
     * Returns the URL to query, with any parameters applied.
     *
     * @return  string
     */
    public function getURL() {
        $hostname = 'http://api.'.$this->env.'.example.com';
    
        $queryString = [
            'page' => $this->getParameter('page', 1),
            'perPage' => $this->getParameter('perPage', 25)
        ];
        return $hostname.'/articles.json?'.http_build_query($queryString);
    }
        
    ...
}
```

```php
$query = (new ArticlesQuery())
    ->setEnvironment(ArticlesQuery::TEST)
    ->setPage(2)
    ->setPerPage(50);
    
$result = $service->fetch($query);
```

If you try to use an environment name that is not defined in `$validEnvironments` you'll get an
`\InvalidArgumentException` thrown.

## transformPayload

The `transformPayload()` function is responsible for taking the raw response from a request and turning it into
something more useful for your application. In our basic example above we simply decoded the JSON and returned the object
however we could do something more useful like:

```php
class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    ...
    
    /**
     * Given a response from the webservice, this function is called to transform it into something
     * a bit more useful for output.
     *
     * This may be passed a false or a null if the call to the webservice fails, so unit test appropriately.
     *
     * @param   mixed   $response
     * @param   array   $headers
     * @return  mixed
     */
    public function transformPayload($response, array $headers) {
        if ($response) {
            return new ArticlesList(json_decode($response));
        }
        return false;
    }
    
    ...
}
```

If the request failed to return (either by a "bad" response status, or a timeout) you'll be passed a `null` here.

You are also given the headers that the response saw from the Query as a key->value array.

## Request Headers

Some APIs will require you to set headers on your requests (usually API keys or User-Agents).

Your query class can define these by overriding/implementing the `getRequestHeaders()` method:
 
```php
class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    ...
    
    /**
     * Returns the headers to send with any request to this service.
     *
     * @return  array
     */
    public function getRequestHeaders()
    {
        return [
            'User-Agent' => 'MyApp v1.0'  
        ];
    }
    
    ...
}
```

## Request Options

For some backends you may need to use a different verifying CA certificate, or tweak some other Guzzle config
setting. The `overrideRequestOptions()` function gives you a hook into that process:

```php
class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    ...
    
    /**
     * Gives services a hook to modify the request options being sent with a request.
     *
     * @param   array   $options
     * @return  array
     */
    public function overrideRequestOptions(array $options)
    {
        $options['verify'] = '/etc/ca/example.com.pem';
        return $options;
    }
    
    ...
}
```

The keys in the array should correspond to the [Guzzle Request Options](http://docs.guzzlephp.org/en/latest/request-options.html)
you're overriding.

**Note**: make sure to modify the array and NOT overwrite it (unless you know what you're doing) as WebserviceKit
may have put values in there already by the time it reaches your Query class!

## Thresholds

WebserviceKit provides automatic monitoring of slow queries, and also provides a feature called "adaptive timeouts"
which can squeeze a little more performance out of your backends when coupled with the Cache.

### getSlowThreshold()

This method simply returns a value (in milliseconds) to use as the slow threshold for requests to this query. Anything
clocked above this value will trigger the "slow response" monitoring hooks.

```php
class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    ...
    
    /**
     * Returns the slow threshold for this service, in milliseconds!
     *
     * @return  int
     */
    public function getSlowThreshold()
    {
        return 300;
    }
    
    ...
}
```

### Adaptive Timeouts

This is one of WebserviceKit's cleverest performance tricks.

WebserviceKit uses the [Stale-while-revalidate](https://github.com/bbc/ipr-php-cache#stale-while-revalidate-caching)
pattern of caching from the `BBC\iPlayerRadio\Cache` library. This allows WebserviceKit to know which requests are super
important to allow to succeed (expired cache items), but be more relaxed about refreshing stale items.

This manifests in adaptive timeouts. Put simply; you can define one set of cURL timeouts for "stale" cache items that is
aggressively short ensuring that the majority of users get a super-fast experience, and another set of cURL timeouts for
expired content where it's imperative to get a response otherwise you have no website!

This sounds complex, but it's extremely simple to implement:

```php
class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    ...
    
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
        return ['connect_timeout' => 1, 'timeout' => 1]; 
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
    ...
}
```

If you wish to disable adaptive timeouts, simply override the `getShortTimeouts` and `getLongTimeouts` methods and
set them to the same values.

## Caching

Caching your backend responses is vital to ensuring not only speed but stability.

WebserviceKit attempts to be a good citizen by reading the Cache-Control headers from the response and using those values,
however if the provider does not provide values, your Query class can define them instead.

WebserviceKit hopes to see a Cache-Control header containing something like this:

```
Cache-Control: max-age=120, must-revalidate, public, s-maxage=120, stale-while-revalidate=30 
```

The portions it's interested in are:

| max-age | The "expires" time used in the cache |
| s-maxage | Same as above if max-age is not present |
| stale-while-revalidate | The "stale" time used in the cache |

If max-age is not defined in the headers, WebserviceKit will use `getMaxAge()` from the Query class.

If stale-while-revalidate is not defined in the headers, WebserviceKit will use `getStaleAge()` from the Query class.

**Note**: it is not possible to override the Cache-Control header from a response! `getMaxAge` and `getStaleAge` are
only called if the header values are not present. This is by design; APIs should be using these values to control their
rate of request and clients should be good citizens.

## Circuit Breakers

WebserviceKit makes (optional) use of the [Solution10\CircuitBreaker](https://github.com/solution10/circuitbreaker)
library to protect backends.
 
If a backend returns an error response, the breaker will have a "failure" reported to it. If the breaker sees enough
failures, it'll "trip" causing WebserviceKit to stop requesting data from it to give it time to recover.

If the Breaker is in tripped state, but there are cached items present, you'll still get them. WebserviceKit just won't
make any new requests for data.

Assign a breaker to your query like so:

```php
$query = (new ArticlesQuery())
    ->setEnvironment(ArticlesQuery::TEST)
    ->setCircuitBreaker(new Solution10\CircuitBreaker\CircuitBreaker('articles', new RedisCache()))
    ->setPage(2)
    ->setPerPage(50);
    
$result = $service->fetch($query);
```

**Tip**: that's ugly as sin. Make use of the Service objects' `beforeQuery()` method and the CircuitBreaker's BreakerBoard
class to make this prettier:

```php
$cacheAdapter = new RedisCache();
$cache = new BBC\iPlayerRadio\Cache\Cache($cacheAdapter);
$client = new GuzzleHttp\Client();
$board = new Solution10\CircuitBreaker\BreakerBoard($cacheAdapter);

$service = new BBC\iPlayerRadio\WebserviceKit\Service($client, $cache);
$service->beforeQuery(function (BBC\iPlayerRadio\WebserviceKit\QueryInterface $query) use ($board) {
    $query->setCircuitBreaker($board->getBreaker($query->getServiceName());
    return $query;
});

```

That way, none of your queries need to worry about explicitly setting the circuit breaker (unless they want to!)

## isFailureState

For certain backends, a 404 is a critical error that needs investigation. For others, it's a totally normal and cool
response. You don't want your monitoring firing and circuit breakers tripping just because a response came back as
a non-200 status code.

`QueryInterface::isFailureState` gives you the chance to take an `\Exception` and return whether its a "true" failure
or not:

```php
class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    ...
    
    /**
     * Given an Exception, return whether this should be considered a "failed" response and the breaker and monitoring
     * informed of it. Useful for when you don't want to trigger alarms because of 404s etc that Guzzle considers
     * to be Exceptions.
     *
     * @param   \Exception  $e
     * @return  bool
     */
    public function isFailureState(\Exception $e)
    {
        if ($e instanceof \GuzzleHttp\Exception\ServerException && $e->getResponse()->getStatus() === 404) {
            return false;
        }
        return true;
    }
    
    ...    
}

```
