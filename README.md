# BBC\iPlayerRadio\WebserviceKit

A powerful layer for reading data from webservices in a fast, extendable and resilient way.

[![Build Status](https://travis-ci.org/bbc/ipr-php-webservicekit.svg?branch=master)](https://travis-ci.org/bbc/ipr-php-webservicekit)
[![Latest Stable Version](https://poser.pugx.org/bbc/ipr-webservicekit/v/stable.svg)](https://packagist.org/packages/bbc/ipr-webservicekit)
[![Total Downloads](https://poser.pugx.org/bbc/ipr-webservicekit/downloads.svg)](https://packagist.org/packages/bbc/ipr-webservicekit)
[![License](https://poser.pugx.org/bbc/ipr-webservicekit/license.svg)](https://packagist.org/packages/bbc/ipr-webservicekit)

- [Requirements](#requirements)
- [Features](#features)
- [Background](#background)
- [Basic Usage](#basic-usage)
- [Documentation](#documentation)

## Requirements

- PHP >= 5.6

## Features

- Resilient, stale-while-revalidate caching at the core
- Full monitoring and logging hooks
- Lightweight (usually a single class) integration of webservices
- Variable cURL timeouts based on cache state
- Multi-curl requests
- Circuit breaker protection for backends
- Framework agnostic
- Highly tested and battle-proven
- Unit testing helper traits to simplify mocking webservices

## Background

This library makes it easy to read data from RESTful or HTTP based APIs, be they public, API key gated or via
SSL certificates.

To integrate a new backend, the library consumer simply needs to create a class that implements
`BBC\iPlayerRadio\WebserviceKit\QueryInterface` (you can even just subclass the `BBC\iPlayerRadio\WebserviceKit\Query` class
that covers most of the essentials!)

These queries tell WebserviceKit how to communicate with the backend service, how to cache it and what to do with
the responses it receives.

## Basic Usage

You will only need a single instance of WebserviceKit within your app, which you then pass Query's into.

```php
$client = new GuzzleHttp\Client();
$cache = new BBC\iPlayerRadio\Cache\Cache(new RedisCache());

$service = new BBC\iPlayerRadio\WebserviceKit\Service(
    $client,
    $cache
);
```

You can then define Query classes:

```php
<?php

use BBC\iPlayerRadio\WebserviceKit\Query;

class MyBackendQuery extends Query
{
    /**
     * Returns the URL to query, with any parameters applied.
     *
     * @return  string
     */
    public function getURL()
    {
        return 'http://my.service:8080/;
    }

    /**
     * Returns a friendly (and safe) name of the webservice this query hits which we can use in
     * error logging and circuit breakers etc. [a-z0-9-_] please.
     *
     * @return  string
     */
    public function getServiceName()
    {
        return 'my.service';
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
    public function transformPayload($response, array $headers)
    {
        $this->lastHeaders = $headers;
        return $response ? json_decode($response) : $response;
    }
}

```

Fetching this queries is then as simple as:

```php
$response = $service->fetch($query);
```

You can also run multiple queries at the same time:

```php
list($response1, $response2) = $service->multiFetch([$query1, $query2]);
```

This is the most basic usage of WebserviceKit, there's a lot more power beneath the hood should you need it.
 
## Documentation

Full documentation can be found in the docs/ folder of the repo!
