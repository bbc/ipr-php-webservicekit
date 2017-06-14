<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\ServiceTests;

use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use GuzzleHttp\Psr7\Response;

class MultiFetchTest extends TestCase
{
    use GetMockedService;

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
}
