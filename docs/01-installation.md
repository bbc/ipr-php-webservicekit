# Installation

WebserviceKit is a straightforward Composer package, installation is as simple as:

```sh
$ composer require bbc/ipr-webservicekit
```

## Base dependencies

- PHP 5.6+ (including PHP 7.0)
- cURL (or another means for [Guzzle](https://github.com/guzzle/guzzle)) to talk to the outside world.

## Recommended

- A caching backend (Redis, Memcached etc). WebserviceKit can use file caching, however for performance you are
recommended to use a "real" caching engine. Anything supported by [Doctrine\Common\Cache](https://github.com/doctrine/cache)
is also supported by us.
- A logging / monitoring solution for tracking your backend queries and performance (Amazon Cloudwatch for instance,
other providers are available.)

## Framework Integration

None! This library is designed to be as "pure PHP" as possible so you can use it with anything.

That said, we do provide some helpers if you happen to use the [Symfony Profiler](https://symfony.com/doc/current/profiler.html)
to aide in your development.
