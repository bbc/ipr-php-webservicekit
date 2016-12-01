<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests;

use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Query;

class QueryTest extends TestCase
{
    public function testSaneDefaults()
    {
        $query = new Query();
        $this->assertEquals([], $query->getRequestHeaders());
        $this->assertEquals(['timeout' => 10], $query->overrideRequestOptions(['timeout' => 10]));
        $this->assertEquals(3000, $query->getSlowThreshold());
        $this->assertEquals(['connect_timeout' => 1, 'timeout' => 3], $query->getShortTimeouts());
        $this->assertEquals(['connect_timeout' => 10, 'timeout' => 10], $query->getLongTimeouts());
        $this->assertEquals(60, $query->getStaleAge());
        $this->assertEquals(300, $query->getMaxAge());
        $this->assertNull($query->getForcedMaxAge());
        $this->assertNull($query->getForcedStaleAge());
        $this->assertTrue($query->isFailureState(new \Exception()));
        $this->assertEquals(md5($query->getURL()), $query->getCacheKey());
        $this->assertTrue($query->canCache());
    }

    public function testSetGetEnvironment()
    {
        /* @var     Query   $query  */
        $query = new Query();
        $this->assertEquals(Query::LIVE, $query->getEnvironment());

        $this->assertEquals($query, $query->setEnvironment(Query::STAGE));
        $this->assertEquals(Query::STAGE, $query->getEnvironment());
    }

    /**
     * @expectedException           \InvalidArgumentException
     * @expectedExceptionMessage    "unknown" is not a supported environment for
     */
    public function testSettingInvalidEnvironment()
    {
        /* @var     Query   $query  */
        $query = new Query();
        $query->setEnvironment('unknown');
    }

    public function testSetGetParameter()
    {
        /* @var     Query   $query  */
        $query = new Query();
        $this->assertNull($query->getParameter('lang'));
        $this->assertEquals('en-GB', $query->getParameter('lang', 'en-GB'));

        $this->assertEquals($query, $query->setParameter('lang', 'es-ES'));
        $this->assertEquals('es-ES', $query->getParameter('lang'));
        $this->assertEquals('es-ES', $query->getParameter('lang', 'en-GB'));
    }

    public function testForceMaxStale()
    {
        $query = new Query();
        $this->assertEquals($query, $query->forceMaxAge(1000));
        $this->assertEquals(1000, $query->getForcedMaxAge());

        $this->assertEquals($query, $query->forceStaleAge(500));
        $this->assertEquals(500, $query->getForcedStaleAge());
    }

    public function testToString()
    {
        $query = new Query();
        $this->assertEquals($query->getURL(), (string)$query);
    }

    public function testConfig()
    {
        /* @var     Query   $query  */
        $query = new Query();
        $this->assertEquals([], $query->getConfig());
        $this->assertEquals($query, $query->setConfig(['api_key' => 'some value']));
        $this->assertEquals(['api_key'=>'some value'], $query->getConfig());
    }

    /* ------------ Testing cache lifetime calculations ----------- */

    public function testCacheAgesNoHeaders()
    {
        $query = new Query();
        list($staleAge, $maxAge) = $query->getCacheAges();

        $this->assertEquals(60, $staleAge);
        $this->assertEquals(300, $maxAge);
    }

    public function testCacheAgesOnlyMax()
    {
        $query = new Query();
        list($staleAge, $maxAge) = $query->getCacheAges('max-age=1000');

        $this->assertEquals(60, $staleAge);
        $this->assertEquals(1000, $maxAge);
    }

    public function testCacheAgesOnlyStale()
    {
        $query = new Query();
        list($staleAge, $maxAge) = $query->getCacheAges('stale-while-revalidate=10');

        $this->assertEquals(10, $staleAge);
        $this->assertEquals(300, $maxAge);
    }

    public function testCacheAgesBoth()
    {
        $query = new Query();
        list($staleAge, $maxAge) = $query->getCacheAges('max-age=1000,stale-while-revalidate=10');

        $this->assertEquals(10, $staleAge);
        $this->assertEquals(1000, $maxAge);
    }

    public function testCacheForcingNoHeaders()
    {
        $query = new Query();
        $query
            ->forceMaxAge(2000)
            ->forceStaleAge(200);

        list($staleAge, $maxAge) = $query->getCacheAges();

        $this->assertEquals(200, $staleAge);
        $this->assertEquals(2000, $maxAge);
    }

    public function testCacheForcingWithHeaders()
    {
        $query = new Query();
        $query
            ->forceMaxAge(2000)
            ->forceStaleAge(200);

        list($staleAge, $maxAge) = $query->getCacheAges('max-age=1000,stale-while-revalidate=10');

        $this->assertEquals(200, $staleAge);
        $this->assertEquals(2000, $maxAge);
    }

    public function testCacheForcingOnlyMax()
    {
        $query = new Query();
        $query
            ->forceMaxAge(2000);

        list($staleAge, $maxAge) = $query->getCacheAges('max-age=1000,stale-while-revalidate=10');

        $this->assertEquals(10, $staleAge);
        $this->assertEquals(2000, $maxAge);

        $query = new Query();
        $query
            ->forceMaxAge(2000)
            ->forceStaleAge(200);

        list($staleAge, $maxAge) = $query->getCacheAges('max-age=1000');

        $this->assertEquals(200, $staleAge);
        $this->assertEquals(2000, $maxAge);
    }

    public function testCacheForcingOnlyStale()
    {
        $query = new Query();
        $query
            ->forceStaleAge(200);

        list($staleAge, $maxAge) = $query->getCacheAges('max-age=1000,stale-while-revalidate=10');

        $this->assertEquals(200, $staleAge);
        $this->assertEquals(1000, $maxAge);

        $query = new Query();
        $query
            ->forceMaxAge(2000)
            ->forceStaleAge(200);

        list($staleAge, $maxAge) = $query->getCacheAges('stale-while-revalidate=1000');

        $this->assertEquals(200, $staleAge);
        $this->assertEquals(2000, $maxAge);
    }

    public function testNoCacheDirective()
    {
        $query = new Query();
        list($staleAge, $maxAge) = $query->getCacheAges('no-cache,max-age=10,stale-while-validate=1');

        $this->assertFalse($staleAge);
        $this->assertFalse($maxAge);
    }

    public function testNoCacheWithForce()
    {
        $query = new Query();
        $query
            ->forceMaxAge(2000)
            ->forceStaleAge(200);

        list($staleAge, $maxAge) = $query->getCacheAges('no-cache,max-age=10,stale-while-validate=1');

        $this->assertEquals(200, $staleAge);
        $this->assertEquals(2000, $maxAge);
    }
}
