<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests;

use BBC\iPlayerRadio\Cache\Cache;
use BBC\iPlayerRadio\WebserviceKit\NoResponseException;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\QueryInterface;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Monitoring;
use BBC\iPlayerRadio\WebserviceKit\Stubs\OtherQuery;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Query;
use Doctrine\Common\Cache\ArrayCache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Solution10\CircuitBreaker\CircuitBreaker;

class ServiceTest extends TestCase
{
    use GetMockedService;

    /* --------------- Property Tests -------------------- */

    public function testSetGetClient()
    {
        $service = $this->getMockedService();
        $this->assertEquals($this->client, $service->getClient());

        $newClient = new Client();
        $newClient->mark = 'red';
        $this->assertEquals($service, $service->setClient($newClient));

        $this->assertEquals($newClient, $service->getClient());
        $this->assertEquals('red', $service->getClient()->mark);
    }

    public function testSetGetCache()
    {
        $service = $this->getMockedService();
        $this->assertEquals($this->cache, $service->getCache());

        $newCache = new Cache(new ArrayCache());
        $newCache->mark = 'green';
        $this->assertEquals($service, $service->setCache($newCache));

        $this->assertEquals($newCache, $service->getCache());
        $this->assertEquals('green', $service->getCache()->mark);
    }

    public function testSetGetMonitoring()
    {
        $service = $this->getMockedService();
        $this->assertEquals($this->monitor, $service->getMonitoring());

        $newMonitor = new Monitoring();
        $newMonitor->mark = 'green';
        $this->assertEquals($service, $service->setMonitoring($newMonitor));

        $this->assertEquals($newMonitor, $service->getMonitoring());
        $this->assertEquals('green', $service->getMonitoring()->mark);
    }

    public function testSetGetBreaker()
    {
        $service = $this->getMockedService();
        $this->assertEquals($this->circuitBreaker, $service->getCircuitBreaker());

        $newBreaker = new CircuitBreaker('tests', new ArrayCache());
        $newBreaker->mark = 'yellow';
        $this->assertEquals($service, $service->setCircuitBreaker($newBreaker));

        $this->assertEquals($newBreaker, $service->getCircuitBreaker());
        $this->assertEquals('yellow', $service->getCircuitBreaker()->mark);
    }

    public function testBeforeQuery()
    {
        $service = $this->getMockedService();
        $this->assertEquals($service, $service->beforeQuery(function ($query) {
            return $query;
        }));
    }

    /* --------------- Empty Cache Tests ---------------------- */

    public function testSimpleFetchEmptyCache()
    {
        // Mock the service and query:
        $service = $this->getMockedService([
            '{"message": "hi there"}'
        ]);
        $query = $this->getMockedQuery();

        // Fetch the data and check that we've returned correctly:
        $response = $service->fetch($query);
        $this->assertInstanceOf('stdClass', $response);
        $this->assertEquals('hi there', $response->message);

        // check that it's been entered into the cache and the stale time set
        $cache = $this->cache->getAdapter();
        $this->assertTrue($cache->contains($query->getCacheKey()));
        $contents = $cache->fetch($query->getCacheKey());
        $this->assertTrue(array_key_exists('bestBefore', $contents));
        $this->assertEquals(60, $contents['bestBefore']);

        // Also check that we told monitoring about it:
        $this->assertEquals(1, $this->monitor->getApisCalled()['unit_tests']);
        $this->assertEquals('unit_tests', $this->monitor->getResponseTimes()[0]['service']);
        $this->assertEquals($query->getURL(), $this->monitor->getResponseTimes()[0]['url']);
        $this->assertInternalType('integer', $this->monitor->getResponseTimes()[0]['time']);
    }

    public function testSimpleFetchPassesHeaders()
    {
        // Mock the service and query:
        $service = $this->getMockedService([
            new Response(
                200,
                ['Content-Type' => 'application/json', 'X-Rate-Limit' => 20],
                '{"message": "hi there"}'
            )
        ]);
        $query = $this->getMockedQuery();

        // Fetch the data and check that we've returned correctly:
        $service->fetch($query);
        $this->assertEquals(
            ['Content-Type' => ['application/json'], 'X-Rate-Limit' => [20]],
            $query->getLastHeaders()
        );

        // Check that the headers are in the cache:
        $cache = $this->cache->getAdapter();
        $contents = $cache->fetch($query->getCacheKey());
        $this->assertEquals(
            ['Content-Type' => ['application/json'], 'X-Rate-Limit' => [20]],
            $contents['payload']['headers']
        );
    }

    public function testFetch500EmptyCache()
    {
        // Mock the service and query:
        $service = $this->getMockedService([
            500
        ]);
        $query = $this->getMockedQuery();

        // Fetch the data
        $result = $service->fetch($query);
        $this->assertFalse($result);

        // Check that we monitored:
        $this->assertEquals(1, $this->monitor->getApisCalled()['unit_tests']);
        $this->assertInstanceOf(
            'GuzzleHttp\\Exception\\ServerException',
            $this->monitor->getExceptions()[0]['exception']
        );
        $this->assertContains(
            '500 Internal Server Error',
            $this->monitor->getExceptions()[0]['exception']->getMessage()
        );

        // And that we didn't cache the error
        $cache = $this->cache->getAdapter();
        $this->assertFalse($cache->contains($query->getCacheKey()));
    }

    public function testFetch404EmptyCache()
    {
        // Mock the service and query:
        $service = $this->getMockedService([
            404
        ]);
        $query = $this->getMockedQuery();

        // Fetch the data
        $result = $service->fetch($query);
        $this->assertFalse($result);

        // Check that we monitored:
        $this->assertEquals(1, $this->monitor->getApisCalled()['unit_tests']);
        $this->assertInstanceOf(
            'GuzzleHttp\\Exception\\ClientException',
            $this->monitor->getExceptions()[0]['exception']
        );
        $this->assertContains(
            '404 Not Found',
            $this->monitor->getExceptions()[0]['exception']->getMessage()
        );

        // And that we didn't cache the error
        $cache = $this->cache->getAdapter();
        $this->assertFalse($cache->contains($query->getCacheKey()));
    }

    /**
     * Tests that any Cache-Control headers coming from the response are used over the ones
     * configured in the webservice class.
     */
    public function testCacheHeadersOverride()
    {
        $ccHeader = 'max-age=1000, stale-while-revalidate=500';

        // Mock the service and query:
        $service = $this->getMockedService([
            new Response(200, ['Cache-Control' => $ccHeader], '{"message": "hi there"}')
        ]);
        $query = $this->getMockedQuery();

        // Fetch the data
        $service->fetch($query);

        // Ensure that we cached correctly:
        $cache = $this->cache->getAdapter();
        $this->assertTrue($cache->contains($query->getCacheKey()));
        $contents = $cache->fetch($query->getCacheKey());
        $this->assertTrue(array_key_exists('bestBefore', $contents));
        $this->assertEquals(500, $contents['bestBefore']);
        $this->assertEquals('{"message": "hi there"}', $contents['payload']['body']);
    }

    /* ----------------- Cached Response Tests ----------------- */

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

    /* ----------------- Circuit Breaker Tests --------------------------- */

    public function testCircuitBreakerTrips()
    {
        $service = $this->getMockedService([
            500, 500, 500, 500, 500
        ]);
        $query = $this->getMockedQuery();

        // Create and set the breaker just so we can read it's state from out here.
        $breaker = $service->getCircuitBreaker();

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
        $breaker = $service->getCircuitBreaker();

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

    /* --------------- Unusual Situation Responses ----------------------- */

    public function testSlowResponse()
    {
        $service = $this->getMockedService();
        $query = $this->getMockedQuery();

        $service->monitorResponseTime($query, 5000);

        // Verify monitoring:
        $this->assertCount(1, $this->monitor->getSlowResponses());
        $this->assertEquals('unit_tests', $this->monitor->getSlowResponses()[0]['service']);
        $this->assertEquals('http://localhost/webservicekit', $this->monitor->getSlowResponses()[0]['url']);
        $this->assertInternalType('integer', $this->monitor->getSlowResponses()[0]['time']);

        $this->assertCount(1, $this->monitor->getResponseTimes());
        $this->assertEquals('unit_tests', $this->monitor->getResponseTimes()[0]['service']);
        $this->assertEquals('http://localhost/webservicekit', $this->monitor->getResponseTimes()[0]['url']);
        $this->assertInternalType('integer', $this->monitor->getResponseTimes()[0]['time']);
    }

    public function testServerException()
    {
        $service = $this->getMockedService([
            new ServerException('Bad Server Response', new Request('GET', 'unittests'), new Response(500))
        ]);
        $query = $this->getMockedQuery('unittests');

        $this->assertFalse($service->fetch($query));

        // Verify the monitoring:
        $this->assertEquals(1, $this->monitor->getApisCalled()['unit_tests']);
        $this->assertCount(1, $this->monitor->getExceptions());
        $this->assertContains('Bad Server Response', $this->monitor->getExceptions()[0]['exception']->getMessage());
    }

    public function testUnknownException()
    {
        $service = $this->getMockedService([
            new \Exception('Unknown error occurred.', 27)
        ]);
        $query = $this->getMockedQuery('unittests');

        $this->assertFalse($service->fetch($query));

        // Verify the monitoring:
        $this->assertEquals(1, $this->monitor->getApisCalled()['unit_tests']);
        $this->assertCount(1, $this->monitor->getExceptions());
        $this->assertContains('Unknown error occurred', $this->monitor->getExceptions()[0]['exception']->getMessage());
    }

    public function testTimeoutException()
    {
        $service = $this->getMockedService([
            new ConnectException('Connection exception', new Request('GET', 'unittests'))
        ]);
        $query = $this->getMockedQuery('unittests');

        $this->assertFalse($service->fetch($query));

        // Lots should be logged and monitored here:
        $this->assertEquals(1, $this->monitor->getApisCalled()['unit_tests']);

        $this->assertEquals('unit_tests', $this->monitor->getSlowResponses()[0]['service']);
        $this->assertEquals('http://localhost/unittests', $this->monitor->getSlowResponses()[0]['url']);
        $this->assertInternalType('integer', $this->monitor->getSlowResponses()[0]['time']);

        $this->assertCount(1, $this->monitor->getExceptions());
        $this->assertContains('Connection exception', $this->monitor->getExceptions()[0]['exception']->getMessage());
    }

    public function testDNSTimeoutException()
    {
        $service = $this->getMockedService([
            new ConnectException('cURL error 6', new Request('GET', 'unittests'))
        ]);
        $query = $this->getMockedQuery('unittests');

        $this->assertFalse($service->fetch($query));

        // Lots should be logged and monitored here:
        $this->assertEquals(1, $this->monitor->getApisCalled()['unit_tests']);

        $this->assertEquals('unit_tests', $this->monitor->getSlowResponses()[0]['service']);
        $this->assertEquals('http://localhost/unittests', $this->monitor->getSlowResponses()[0]['url']);
        $this->assertInternalType('integer', $this->monitor->getSlowResponses()[0]['time']);

        $this->assertCount(1, $this->monitor->getExceptions());
        $this->assertContains('cURL error 6', $this->monitor->getExceptions()[0]['exception']->getMessage());
    }

    /* ---------------- Multi-Fetch tests ------------------- */

    public function testMutiFetchBasic()
    {
        // Mock the service and queries:
        $service = $this->getMockedService([
            '{"message": "foo"}',
            '{"message": "bar"}'
        ]);
        $queries = [
            $this->getMockedQuery('endpoint1'),
            $this->getMockedQuery('endpoint2')
        ];

        // Fetch the data and check that we've returned correctly:
        $responses = $service->multiFetch($queries);

        $this->assertCount(2, $responses);

        $this->assertInstanceOf('stdClass', $responses[0]);
        $this->assertInstanceOf('stdClass', $responses[1]);

        $this->assertEquals('foo', $responses[0]->message);
        $this->assertEquals('bar', $responses[1]->message);

        // check that both items have entered the cache:
        /*  @var    \Doctrine\Common\Cache\ArrayCache   $cache  */
        $cacheAdapter = $service->getCache()->getAdapter();

        foreach ($queries as $q) {
            $this->assertTrue($cacheAdapter->contains($q->getCacheKey()));
            $contents = $cacheAdapter->fetch($q->getCacheKey());
            $this->assertTrue(array_key_exists('bestBefore', $contents));
            $this->assertEquals(60, $contents['bestBefore']);
        }
    }

    public function testMultiFetchBothFail()
    {
        $service = $this->getMockedService([
            new Response(500, [], '{"message": "foo"}'),
            500
        ]);
        $queries = [
            $this->getMockedQuery('endpoint1'),
            $this->getMockedQuery('endpoint2')
        ];

        // Fetch the data and check that we've returned correctly:
        $responses = $service->multiFetch($queries);

        $this->assertCount(2, $responses);

        $this->assertFalse($responses[0]);
        $this->assertFalse($responses[1]);

        // check that neither item is in the cache
        /*  @var    \Doctrine\Common\Cache\ArrayCache   $cache  */
        $cacheAdapter = $service->getCache()->getAdapter();

        foreach ($queries as $q) {
            $this->assertFalse($cacheAdapter->contains($q->getCacheKey()));
        }

        $this->assertEquals(2, $this->monitor->getApisCalled()['unit_tests']);
        $this->assertCount(2, $this->monitor->getExceptions());
    }

    public function testMultiFetchOneFails()
    {
        // Mock the service and queries:
        $service = $this->getMockedService([
            '{"message": "foo"}',
            500
        ]);
        $queries = [
            $this->getMockedQuery('endpoint1'),
            $this->getMockedQuery('endpoint2')
        ];

        // Fetch the data and check that we've returned correctly:
        $responses = $service->multiFetch($queries);

        $this->assertCount(2, $responses);

        $this->assertInstanceOf('stdClass', $responses[0]);
        $this->assertFalse($responses[1]);
    }

    public function testMultiFetchUsesCache()
    {
        // Mock the service and queries:
        $service = $this->getMockedService([
            '{"message": "bar"}'
        ]);
        $queries = [
            $this->getMockedQuery('endpoint1'),
            $this->getMockedQuery('endpoint2')
        ];

        $service
            ->getCache()
            ->getAdapter()
            ->save($queries[0]->getCacheKey(), [
                'bestBefore'    => 60,
                'storedTime'    => time(),
                'payload'       => ['body' => '{"message": "foo"}', 'headers' => []]
            ]);

        // Fetch the data and check that we've returned correctly:
        $responses = $service->multiFetch($queries);

        $this->assertCount(2, $responses);

        $this->assertInstanceOf('stdClass', $responses[0]);
        $this->assertInstanceOf('stdClass', $responses[1]);

        $this->assertEquals('foo', $responses[0]->message);
        $this->assertEquals('bar', $responses[1]->message);
    }

    /* ------------------ beforeQuery tests -------------------- */

    public function testBeforeQueryNoTypehint()
    {
        $service = $this->getMockedService([
            '{"message": "bar"}'
        ]);
        $query = $this->getMockedQuery();

        $beforeCalled = false;
        $service->beforeQuery(function ($query) use (&$beforeCalled) {
            $beforeCalled = true;
            return $query;
        });

        $service->fetch($query);
        $this->assertTrue($beforeCalled);
    }

    public function testBeforeQueryExactTypehint()
    {
        $service = $this->getMockedService([
            '{"message": "foo"}',
        ]);
        $query = $this->getMockedQuery();

        $beforeCalled = false;
        $service->beforeQuery(function (Query $query) use (&$beforeCalled) {
            $beforeCalled = true;
            return $query;
        });

        $service->fetch($query);
        $this->assertTrue($beforeCalled);

        // Doesn't match:

        $service = $this->getMockedService([
            '{"message": "bar"}',
        ]);
        $query = new OtherQuery();

        $beforeCalled = false;
        $service->beforeQuery(function (Query $query) use (&$beforeCalled) {
            $beforeCalled = true;
            return $query;
        });

        $service->fetch($query);
        $this->assertFalse($beforeCalled);
    }

    public function testBeforeQueryParentClass()
    {
        $service = $this->getMockedService([
            '{"message": "foo"}',
        ]);
        $query = $this->getMockedQuery();

        $beforeCalled = false;
        $service->beforeQuery(function (QueryInterface $query) use (&$beforeCalled) {
            $beforeCalled = true;
            return $query;
        });

        $service->fetch($query);
        $this->assertTrue($beforeCalled);
    }

    public function testBeforeQueryMultipleHandlers()
    {
        $service = $this->getMockedService([
            '{"message": "foo"}',
        ]);
        $query = $this->getMockedQuery();

        $beforeCalled1 = false;
        $beforeCalled2 = false;

        $service->beforeQuery(function (Query $query) use (&$beforeCalled1) {
            $beforeCalled1 = true;
            return $query;
        });

        $service->beforeQuery(function ($query) use (&$beforeCalled2) {
            $beforeCalled2 = true;
            return $query;
        });

        $service->fetch($query);
        $this->assertTrue($beforeCalled1);
        $this->assertTrue($beforeCalled2);
    }

    /**
     * @expectedException           \LogicException
     * @expectedExceptionMessage    beforeHandlers must have at least one parameter!
     */
    public function testBeforeHandlerBadMethodSignature()
    {
        $service = $this->getMockedService([
            '{"message": "foo"}',
        ]);
        $query = $this->getMockedQuery();

        $service->beforeQuery(function () {
            // Bad callback, needs a parameter.
        });

        $service->fetch($query);
    }
}
