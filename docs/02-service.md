# Service Instance

The Service instance is the core of WebserviceKit and is responsible for communicating
with the backends, caching their responses correctly, logging and monitoring the calls and
protecting the backends using circuitbreakers.

Generally speaking, you only need one of these in your application, unless you wish to use a different
cache or guzzle client for a different backend.

- [Creating Service instances](#creating-service-instances)
- [Fetching Data](#fetching-data)
- [Callbacks](#callbacks)
- [Unit testing services](#unit-testing-services)

## Creating Service instances

A Service instance takes two mandatory arguments and one optional.

```php
$client     = new GuzzleHttp\Client();
$cache      = new BBC\iPlayerRadio\Cache\Cache(new RedisCache());
$monitor    = new MonitoringDelegate(); // implements BBC\iPlayerRadio\WebserviceKit\MonitoringInterface

$service = new BBC\iPlayerRadio\WebserviceKit\Service($client, $cache, $monitor);

```

The `$client` parameter is an instance of `GuzzleHttp\Client`. You should apply any standard options for all requests
(proxies, SSL certificates etc) to this `$client` before passing it into `Service`.

The `$cache` parameter is an instance of `BBC\iPlayerRadio\Cache\Cache` and forms the basis of the stale-while-revalidate
and resilient caching provided by the library. See [that projects repo](https://github.com/bbc/ipr-php-cache) for more info.

The `$monitor` is optional and is explained in detail in the [Monitoring](./05-monitoring.md) section.

All three parameters can be accessed and replaced using their respective get and set functions:

```php
$service->setClient($newClient);
$service->getClient();

$service->setCache($newCache);
$service->getCache();

$service->setMonitoring($newMonitor);
$service->getMonitoring();
```

## Fetching Data

The Service instance is capable of fetching data from almost any type of backend thanks to the [Query](./03-queries.md)
classes. Those are covered in more detail in the linked chapter, for now, all you need to know is this; pass one in to
get data:

```php
$query = (new ArticlesQuery())->setId('385243-dfg7f8-fsdfg602');
$article = $service->fetch($query);
```

In the above example, `ArticlesQuery` implements the `BBC\iPlayerRadio\WebserviceKit\QueryInterface` interface, which
allows WebserviceKit to use it to fetch data. The result `$article` is whatever the `ArticlesQuery` transformed it into
(see [transformPayload](./03-queries.md#transformPayload) for more information).

The Service instance will perform all the cache reading and writing, so all your code needs to do is ask for the data;
WebserviceKit will handle the rest and you'll get the same result regardless of whether it was a "real" request or not.

If for whatever reason WebserviceKit could not fetch the data (timeout, bad response etc), you will instead get a `null`.

You can also fetch multiple queries simultaneously using `multiFetch()`:

```php
$firstArticleQuery = (new ArticlesQuery())->setId(27);
$secondArticleQuery = (new ArticlesQuery())->setId(28);

list($firstArticle, $secondArticle) = $service->multiFetch([$firstArticleQuery, $secondArticleQuery]);
```

These requests will happen together, asynchronously, and then return an array of results (expanded out above using `list()`).
If a request should fail, you'll again get a `null`.

WebserviceKit guarantees that you will always get the same number of results as queries, and they will always be in the same
order, regardless of which response finished first. Put simply, in the above example, `$firstArticle` will always be the
result of `$firstArticleQuery` regardless of cache state, response time or failure.

## Callbacks

WebserviceKit provides a single callback point on the service; `beforeQuery()`. The reason for this sparseness is to
encourage the use of the [Query](./03-queries.md) methods to make requests as specific and unique as possible without
relying on modifying the Service object.

`beforeQuery()` gives you the chance to modify the query just before it's executed. **Note**: this is only called for
*real* requests, not for cached ones. This is the ideal point to inject things like API keys or environments:

```php
$apiKey = $config->get('articles.api_key');
$environment = $config->get('articles.environment');
$service->beforeQuery(function (BBC\iPlayerRadio\WebserviceKit\QueryInterface $query) use ($apiKey, $environment) {
    $query->setAPIKey($apiKey);
    $query->setEnvironment($environment);
    return $query;
});
```

This allows the Query subclasses to focus on providing their unique properties rather than mundane information that is
common to all.

## Unit testing services

WebserviceKit wants to help you in your testing! To that end, it's super simple to unit test requests through WebserviceKit.

The library provides a trait: `BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService` which quickly allows you to
set up a fully mocked Service instance, complete with a fake cache and fake Monitoring delegate.

```php
<?php

class MyTestCase extends PHPUnit_Framework_TestCase
{
    use \BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
    
    public function testWhen500()
    {
        // You can pass a single integer to create a response with that status code:
        $service = $this->getMockedService([
            500
        ]);
        
        // Assume that the ArticlesRepository is making ArticlesQuery requests through
        // the WebserviceKit library.
        $articlesRepository = new ArticlesRepository($service);
        $article = $articlesRepository->findById(1);
        $this->assertNull($article);
    }
    
    public function testWhenConnectException()
    {
        // Passing an Exception will cause that to throw:
        $service = $this->getMockedService([
            new \GuzzleHttp\Exception\ConnectException('Count not connect', new \GuzzleHttp\Psr7\Request('GET', '/'))
        ]);
        
        $articlesRepository = new ArticlesRepository($service);
        $article = $articlesRepository->findById(1);
        $this->assertNull($article);
    }
    
    public function testMalformedJson()
    {
        // You can construct a full PSR7\Response object and pass that into the mock:
        $service = $this->getMockedService([
            new \GuzzleHttp\Psr7\Response(200, [], '{"bad json')
        ]);
        
        $articlesRepository = new ArticlesRepository($service);
        $article = $articlesRepository->findById(1);
        $this->assertNull($article);
    }
    
    public function testAllGood()
    {
        // And passing a naked string will create a 200 response with the string as the body
        $service = $this->getMockedService([
            '{"articles": []}'
        ]);
        
        $articlesRepository = new ArticlesRepository($service);
        $article = $articlesRepository->findById(1);
        $this->assertNull($article);
    }
}

```
