# Fixtures

WebserviceKit contains a feature called "Fixtures". Fixtures allow you to (in-code) define a pre-set set of behaviours
for a given query or queries. Put simply, you can mock out WebserviceKit responses to react in a known, defined way!

The aim is to allow you to fully automate your applications failure states by triggering changes in WebserviceKit via
the URL. So you can do hit the following URL:

```
http://myapp.com/articles?_fixture=Articles500
```

and see what happens when your Articles backend service is totally dead!

This feature works best with the [Silex microframework](http://silex.sensiolabs.org) and we have a ServiceProvider to
make integrating this a doddle.

- [Defining Fixtures](#defining-fixtures)
- [Fixture Conditions](#fixture-conditions)
- [Fixture Returns](#fixture-returns)
- [Using Fixtures in your app](#using-fixtures-in-your-app)
- [Custom Fixture Loaders](#custom-fixture-loaders)
- [Debugging Fixtures](#debugging-fixtures)
- [How real are Fixtures?](#how-real-are-fixtures)

## Defining Fixtures

Let's say I want to see what happens to my app when the Articles service returns a 500.

I have an Articles query that looks like this:

```php
<?php

class ArticlesQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    public function getURL() {
        return 'http://api.example.com/articles.json';
    }
    
    public function getServiceName() {
        return 'articles-service'; 
    }
    
    public function transformPayload($response, array $headers) {
        if ($response) {
            return json_decode($response);
        }
        return false;
    }
}

```

I can write a Fixture that fails all requests to the "articles-service" (see `getServiceName()`) like so:

```php
<?php

use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureDefinition;

class Articles500 extends FixtureDefinition
{
    public function implement()
    {
        $articles = $this->alterService('articles-service');
        $articles
            ->allRequests()
            ->willReturn('', 500);
    }
}
```

In the `implement()` function, we can choose which services we wish to "alter". This is altering and not breaking,
if you do not define any changes, the webservice will continue to work as expected.

```php
$articles = $this->alterService('articles-service');
```

`alterService` takes the service name you wish to alter and returns a wrapped service which you can now modify.

```php
$articles
    ->allRequests()
```

This part starts a "condition" for the service to listen out for. In this case, we're asking for "allRequests" which
means anything that passes through is going to do the following:

```php
    ->willReturn('', 500);
```

It's as simple as that!

**Note**: WebserviceKit will intelligently disable the cache *only* for requests that you have modified. So you can be 
sure that your not seeing a cached version of your call, however remember that other calls *will* be cached.

## Fixture Conditions

Fixture conditions allow you to target your failures down to the exact request you want to fail. They are extremely
powerful and well worth exploring.

We saw the `allRequests` condition in the example above. There are others:

### allRequests()

```php
$service->allRequests();
```

Every request passing through will do the following.

### queryHas() and queryHasNot()

```php
$service->queryHas('sid', 'bbc_radio_one');
$service->queryHasNot('limit', 20);
```

As we know, Webservice requests are defined by [Queries](./03-queries.md). We can
ask the Fixture system to listen for certain parameters in these queries and trigger behaviour based on that.

`queryHas(key, value)` - if the query has a given key with a given value, it'll match
 
`queryHasNot(key, value)` - matches if the query does not have key set to value.

You can mix and match `queryHas` and `queryHasNot` into some quite complex statements. For example, I want to return
a given blob of JSON for everyone who is not bbc_radio_one or bbc_1xtra and has a type of 'music_network':

```php
$service
    ->queryHasNot('sid', 'bbc_radio_one')
    ->queryHasNot('sid', 'bbc_1xtra')
    ->queryHas('type', 'music_network')
    ->willReturn('{"messages": []}', 200);
```

## Fixture Returns

The `willReturn` function is the main way of defining what your fixtures will, well, return! It takes three parameters
to control what kind of response you want to give.

```php
public function willReturn($body, $status = 200, array $headers = [])
```

The first parameter is a string for the body of the response. The second parameter is the status code of the response,
if you don't give one, it'll default to 200. There's also an optional third parameter which is an array of HTTP headers.

You can also pass an anonymous function to build your own response body, based on the query:

```php
$service
    ->allRequests()
    ->willReturn(function (Query $query) {
        return '{"'.$query->getParameter('sid').'": {"messages": []}}';
    }, 200);
```

Or even return a full response instance itself:

```php
$service
    ->allRequests()
    ->willReturn(function (Query $query) {
        return new GuzzleHttp\PSR7\Response(500, [], 'error!');
    });
```

## Using Fixtures in your app

So how do you get these fixtures to work in your app?

If you're using Silex, it's as simple as:

```php
<?php

//
// Assuming that all my Fixtures live under the MyApp\Fixtures and AnotherApp\\Fixtures namespaces.
//

$app = new Silex\Application();

$fixtureServiceProvider = new \BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureServiceProvider();

// Register a FixtureLoader. Most of the time, the namespace one will work for you:
$fixtureLoader = new \BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureLoader\NamespaceLoader();
$fixtureLoader->setNamespaces([
    'MyApp\\Fixtures',
    'AnotherApp\\Fixtures'
]);
$fixtureServiceProvider->addFixtureLoader($fixtureLoader);

$app->register($fixtureServiceProvider);

```

And it'll work! If you're using something else, you'll want to look at the FixtureServiceProvider code
to see what it's doing.

## Custom Fixture Loaders

If the `NamespaceLoader` doesn't work for you, you can define your own FixtureLoader by creating a class
that implements `BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureLoaderInterface`.

This is a very simple interface with just one method:
`public function loadFixtureDefinition($fixtureName, FixtureService $fixtureService, Request $request)`
and is expected to return the `FixtureDefinition` subclass associated with the given name, or `false` if this
loader doesn't understand the fixture name.

## Debugging Fixtures

If you're using the Symfony Profiler with Silex, by registering the `ProfilerCollectorsServiceProvider` you will
be given Fixture information within the debugbar:

```php
$app->register(new BBC\iPlayerRadio\WebserviceKit\ProfilerCollectorsServiceProvider());
```

You'll also get some awesome profiler information about Guzzle!

## How real are fixtures?

Fixtures operate by using Guzzle's own internal mocking facilities, the code-path change within WebserviceKit is
extremely small, ensuring that your app will see no difference between a Fixture and a set of real requests.
