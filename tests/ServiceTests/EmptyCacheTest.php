<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\ServiceTests;

use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use GuzzleHttp\Psr7\Response;

class EmptyCacheTest extends TestCase
{
    use GetMockedService;

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
            ['Content-Type' => ['application/json'], 'X-Rate-Limit' => [20], 'statusCode' => 200],
            $query->getLastHeaders()
        );

        // Check that the headers are in the cache:
        $cache = $this->cache->getAdapter();
        $contents = $cache->fetch($query->getCacheKey());
        $this->assertEquals(
            ['Content-Type' => ['application/json'], 'X-Rate-Limit' => [20], 'statusCode' => 200],
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

    public function testForcingCacheLifetimeNoHeaders()
    {
        $service = $this->getMockedService([
            new Response(200, [], '{"message": "hi there"}')
        ]);
        $query = $this->getMockedQuery();

        $query
            ->forceMaxAge(2000)
            ->forceStaleAge(200);

        $service->fetch($query);

        $cache = $this->cache->getAdapter();
        $this->assertTrue($cache->contains($query->getCacheKey()));
        $contents = $cache->fetch($query->getCacheKey());
        $this->assertTrue(array_key_exists('bestBefore', $contents));
        $this->assertEquals(200, $contents['bestBefore']);
        $this->assertEquals('{"message": "hi there"}', $contents['payload']['body']);
    }

    public function testForcingCacheLifetimeWithHeaders()
    {
        $ccHeader = 'max-age=1000, stale-while-revalidate=500';

        $service = $this->getMockedService([
            new Response(200, ['Cache-Control' => $ccHeader], '{"message": "hi there"}')
        ]);
        $query = $this->getMockedQuery();

        $query
            ->forceMaxAge(2000)
            ->forceStaleAge(200);

        $service->fetch($query);

        $cache = $this->cache->getAdapter();
        $this->assertTrue($cache->contains($query->getCacheKey()));
        $contents = $cache->fetch($query->getCacheKey());
        $this->assertTrue(array_key_exists('bestBefore', $contents));
        $this->assertEquals(200, $contents['bestBefore']);
        $this->assertEquals('{"message": "hi there"}', $contents['payload']['body']);
    }

    public function testNoCacheDirective()
    {
        $ccHeader = 'no-cache, max-age=1000, stale-while-revalidate=500';

        $service = $this->getMockedService([
            new Response(200, ['Cache-Control' => $ccHeader], '{"message": "hi there"}')
        ]);
        $query = $this->getMockedQuery();

        $service->fetch($query);

        $this->assertFalse(
            $this->cache->getAdapter()->contains($query->getCacheKey())
        );
    }

    public function testNoCacheDirectiveWithForce()
    {
        $ccHeader = 'no-cache, max-age=1000, stale-while-revalidate=500';

        $service = $this->getMockedService([
            new Response(200, ['Cache-Control' => $ccHeader], '{"message": "hi there"}')
        ]);
        $query = $this->getMockedQuery();
        $query
            ->forceStaleAge(100)
            ->forceMaxAge(1000);

        $service->fetch($query);

        $this->assertTrue(
            $this->cache->getAdapter()->contains($query->getCacheKey())
        );
    }
}
