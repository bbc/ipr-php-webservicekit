<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\ServiceTests;

use BBC\iPlayerRadio\WebserviceKit\NoResponseException;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use GuzzleHttp\Psr7\Response;

class CircuitBreakerTest extends TestCase
{
    use GetMockedService;

    public function testCircuitBreakerTrips()
    {
        $service = $this->getMockedService([
            500, 500, 500, 500, 500
        ]);
        $query = $this->getMockedQuery();

        // Create and set the breaker just so we can read it's state from out here.
        $breaker = $query->getCircuitBreaker();

        // Fetch five times to trip the breaker;
        for ($i = 0; $i < 5; $i ++) {
            try {
                $service->fetch($query);
            } catch (NoResponseException $e) {
                // ignore it.
            }
            if ($i != 4) {
                $this->assertTrue($breaker->isClosed(), 'breaker closed early ($i = ' . $i . ')');
            }
        }

        // Verify that the breaker tripped:
        $this->assertFalse($breaker->isClosed());
    }

    public function testBreakerOpenDoesntAttemptRerequest()
    {
        // Set up mocking:
        $errorResponse = new Response(500);
        $successResponse = new Response(200);

        // fail 5 times, then succeed
        $service = $this->getMockedService([
            $errorResponse, $errorResponse, $errorResponse, $errorResponse,
            $errorResponse, $successResponse
        ]);
        $query = $this->getMockedQuery();

        // Create and set the breaker just so we can read it's state from out here.
        $breaker = $query->getCircuitBreaker();

        // Fetch five times to trip the breaker;
        for ($i = 0; $i < 5; $i ++) {
            try {
                $service->fetch($query);
            } catch (NoResponseException $e) {
                // ignore it.
            }
            if ($i != 4) {
                $this->assertTrue($breaker->isClosed(), 'breaker closed early ($i = ' . $i . ')');
            }
        }

        // Verify that the breaker tripped:
        $this->assertFalse($breaker->isClosed());

        // Re-request the data, which even though the problem has cleared, the response
        // will throw as the breaker is Open.
        $result = $service->fetch($query);
        $this->assertFalse($result);
    }
}
