<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\ServiceTests;

use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Query404Ok;
use Doctrine\Common\Cache\ArrayCache;
use GuzzleHttp\Psr7\Response;
use Solution10\CircuitBreaker\CircuitBreaker;

class CachedResponseTest extends TestCase
{
    use GetMockedService;

    public function testUsesValidCache()
    {
        $service = $this->getMockedService([
            500
        ]);
        $query = $this->getMockedQuery();

        $service
            ->getCache()
            ->getAdapter()
            ->save($query->getCacheKey(), [
                'bestBefore'    => 60,
                'storedTime'    => time(),
                'payload'       => ['body' => '{"message": "hi there"}', 'headers' => []]
            ]);

        // Make the request and check the response:
        $response = $service->fetch($query);
        $this->assertInstanceOf('stdClass', $response);
        $this->assertEquals('hi there', $response->message);
    }

    /**
     * Cache is stale, but the re-request to the service succeeds
     */
    public function testSuccessfulRefresh()
    {
        // Set up mocking:
        // Mock the service and query:
        $service = $this->getMockedService([
            new Response(200, [], '{"message": "hi there"}')
        ]);
        $query = $this->getMockedQuery();

        // Pre-load the cache:
        $service
            ->getCache()
            ->getAdapter()
            ->save($query->getCacheKey(), [
                'bestBefore'    => 10,
                'storedTime'    => time() - 20,
                'payload'       => [
                    'body' => '{"message": "hi there cached"}', // note the message is different to prove a pull.
                    'headers' => []
                ]
            ]);

        // Make the call and validate the response:
        $response = $service->fetch($query);
        $this->assertEquals('hi there', $response->message);

        // Check that the new response has been entered into cache:
        $this->assertEquals(
            '{"message": "hi there"}',
            $service->getCache()->getAdapter()->fetch($query->getCacheKey())['payload']['body']
        );
    }

    /**
     * Cache is stale, but valid. Refresh fails, fall back.
     */
    public function testUseStale()
    {
        // Mock the service and query:
        $service = $this->getMockedService([
            500
        ]);
        $query = $this->getMockedQuery();

        $service
            ->getCache()
            ->getAdapter()
            ->save($query->getCacheKey(), [
                'bestBefore'    => 10,
                'storedTime'    => time() - 20,
                'payload'       => [
                    'body' => '{"message": "hi there"}',
                    'headers' => []
                ]
            ]);

        // Make the call and validate the response:
        $response = $service->fetch($query);
        $this->assertEquals('hi there', $response->message);

        // Ensure we logged and monitored the failed refresh.
        $this->assertEquals(1, $this->monitor->getApisCalled()['unit_tests']);
        $this->assertInstanceOf(
            'GuzzleHttp\\Exception\\ServerException',
            $this->monitor->getExceptions()[0]['exception']
        );
        $this->assertContains(
            '500 Internal Server Error',
            $this->monitor->getExceptions()[0]['exception']->getMessage()
        );

        // Make sure that we didn't cache the bad response:
        $this->assertEquals(
            '{"message": "hi there"}',
            $service->getCache()->getAdapter()->fetch($query->getCacheKey())['payload']['body']
        );
    }

    public function testErrorStateCanBeCached()
    {
        $service = $this->getMockedService([404]);
        $query = new Query404Ok();
        $query->setCircuitBreaker(new CircuitBreaker('webservicekit_tests', new ArrayCache()));

        $service->fetch($query);

        $this->assertTrue(
            $service->getCache()->getAdapter()->contains($query->getCacheKey())
        );
        $this->assertEquals(404, $query->getLastHeaders()['statusCode']);
    }

    public function testErrorStateReturnsPayload()
    {
        $service = $this->getMockedService([
            new Response(404, [], json_encode(['message' => 'Error Message']))
        ]);
        $query = new Query404Ok();
        $query->setCircuitBreaker(new CircuitBreaker('webservicekit_tests', new ArrayCache()));

        $result = $service->fetch($query);

        $this->assertEquals((object)['message' => 'Error Message'], $result);
        $this->assertEquals(404, $query->getLastHeaders()['statusCode']);
    }
}
