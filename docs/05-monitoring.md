# Monitoring

It's important to know the health of your backends and be able to keep an eye on error rates, response times and the like.

WebserviceKit provides a delegate pattern mechanism for reporting the information about requests; giving you the flexibility
to integrate with whatever logging / monitoring solution that you use.

A simple (but terrible) monitoring delegate could look like this:

```php
<?php

use BBC\iPlayerRadio\WebserviceKit\MonitoringInterface;
use BBC\iPlayerRadio\WebserviceKit\QueryInterface;

class MyMonitoringDelegate implements MonitoringInterface
{
    /**
     * @var     \Psr\Log\LoggerInterface
     */
    protected $logger;
    
    public function __construct(\Psr\Log\LoggerInterface $logger) {
        $this->logger = $logger;
    }
    
    /**
     * WebserviceKit will tell you how many calls were made to the APIs during a multiFetch.
     * This array takes the form:
     *
     *  [
     *      service name => (int) count of calls
     *  ]
     *
     * @param   array   $callCounts
     * @return  $this
     */
    public function apisCalled(array $callCounts) {
        foreach ($callCounts as $service => $count) {
            $this->logger->info('Called '.$service.' '.$count.' times!');
        }
    }
    
    /**
     * @param   string  $serviceName
     * @param   string  $url
     * @param   int     $time
     * @return  $this
     */
    public function slowResponse($serviceName, $url, $time) {
        $this->logger->warning('Slow response! '.$url.' took '.$time.'ms');
    }

    /**
     * @param   string  $serviceName
     * @param   string  $url
     * @param   int     $time
     * @return  $this
     */
    public function responseTime($serviceName, $url, $time) {
        $this->logger->info('Response from '.$serviceName.' on '.$url.' took '.$time.'ms'); 
    }
    
    /**
     * @param   QueryInterface      $query
     * @param   \Exception          $e
     * @return  $this
     */
    public function onException(QueryInterface $query, \Exception $e) {
        $this->logger->error('Query to '.$query->getURL().' triggered '.$e->getMessage()); 
    }
}

```

This is far, FAR too simple to use in production, but highlights the concepts of the Monitoring delegate.
(In reality, you wouldn't log every responseTime, but you might ping it off to a CustomMetric in CloudFormation, as an
example.)

### apisCalled()

This is called at the end of a Query (or Queries if using Multi-Fetch) with a simple count of how many calls
to each API were made. The key here is defined by the [Query::getServiceName()](./03-queries.md) method.

**Hint**: this delegate method when combined with a monitoring system will give you pretty graphs about how many calls
your making to your backends and allow you to accurately predict rate limits etc since this is only tracking *actual*
calls made, not cache reads!

### slowResponse()

When WebserviceKit sees a response from an API that is longer than the [Query::getSlowThresholds()](./03-queries.md) allows,
this delegate method will be called. The serviceName is provided by `QueryInterface::getServiceName()` and the time is
always in milliseconds (even for multi-second response times).

### responseTime()

Every request is timed, and this delegate method called to report how long it took. **This function will always fire, even
if `slowResponse()` was also fired!**

The serviceName is provided by `QueryInterface::getServiceName()` and the time is always in milliseconds.

### onException()

When WebserviceKit encounters any `\Exception` during fetch, this delegate method will be called (as well as anything
that WebserviceKit would normally do). This gives you a hook to monitor things like timeouts, DNS failures, JSON parse
errors and anything else you may wish to.

You'll be passed the Query that failed and the Exception that was triggered. Use type hinting within the method to work
out what to do with the exception.
