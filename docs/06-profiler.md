# Profiler

Getting visibility of your HTTP requests and the like is super important during development (and even on
non-live environments). WebserviceKit provides `Symfony\Component\HttpKernel\DataCollector\DataCollector` instances
for both Guzzle and Fixtures to aide in your development and a Silex service provider to make setting it up in the
Symfony Profiler simple.

- [Registering in Silex](#registering-in-silex)
- [GuzzleDataCollector](#guzzledatacollector)
- [FixturesDataCollector](#fixturesdatacollector)

## Registering in Silex

```php
$app->register(new ProfilerCollectorsServiceProvider());
```

That's it!

## GuzzleDataCollector

This collector tracks the requests going through Service instances to the backend. This data is collected and tracked
regardless of whether or not you defined a monitoring delegate.

## FixturesDataCollector

Tracks the Fixtures that were loaded, and which Guzzle requests matched and were replaced in the course of the Service's
lifetime.
