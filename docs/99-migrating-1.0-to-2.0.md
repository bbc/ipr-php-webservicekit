# Migrating from 1.0 to 2.0

2.0 is not a large update, but does introduce some breaking changes that you need to be aware of.

- [multiFetch() removed](#multifetch-removed)
- [Slow Responses](#slow-responses)
- [MonitoringInterface changes](#monitoringinterface-changes)
- [Issues Fixed](#issues-fixed)

## multiFetch() Removed

The `Service::mutliFetch()` method has been deprecated for a while and can be removed in favour of just passing an
array of `Query` objects to `fetch()`:

**1.x**:

```php
$queries = [new Query(), new Query(), new Query()];
$responses = $service->multiFetch($queries);
```

**2.x**:

```php
$queries = [new Query(), new Query(), new Query()];
$responses = $service->fetch($queries);
```

## Slow Responses

The concept of slow responses has been removed in favour of delegating the tracking of that to the MonitoringInterface.
This means references to the slow thresholds and such have been removed throughout the lib.

## MonitoringInterface changes

`MonitoringInterface` has been updated to give access to a number of new metrics around response times.

- `slowResponse` removed: tracking slow responses is now up to your code.
- `responseTimes` removed in favour of `onTransferStats` which passes the entire `GuzzleHttp\TransferStats` object into
the callback so you can monitor anything that you fancy.

See the `MonitoringInterface` source file for more details.

## Issues Fixed

- Double counting slow response times, leading to inaccurate reporting of downstream API timings.
