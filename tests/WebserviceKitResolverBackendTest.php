<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests;

use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\LoadMockedResponse;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\QueryInterface;
use BBC\iPlayerRadio\WebserviceKit\Service;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Monitoring;
use BBC\iPlayerRadio\WebserviceKit\Stubs\ProgrammesQuery;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Query;
use BBC\iPlayerRadio\WebserviceKit\WebserviceKitResolverBackend;
use GuzzleHttp\Psr7\Response;

class ResolverBackendTest extends TestCase
{
    use GetMockedService;
    use LoadMockedResponse;

    protected function preloadCache(Service $service, QueryInterface $query, $cacheData)
    {
        $service
            ->getCache()
            ->getAdapter()
            ->save($query->getCacheKey(), $cacheData);
    }

    /* --------------- Get / Set Tests ---------------------- */

    public function testGetSetService()
    {
        $service = $this->getMockedService();
        $backend = new WebserviceKitResolverBackend($service);
        $this->assertEquals($service, $backend->getService());

        $newService = $this->getMockedService();
        $newService->mark = 'green'; // this is to force PHP to copy the object.
        $this->assertEquals($backend, $backend->setService($newService));
        $this->assertNotEquals($service, $backend->getService());
        $this->assertEquals($newService, $backend->getService());
    }

    /* -------------- canResolve() tests ---------------------- */

    public function testCanResolvePureQuery()
    {
        $service = $this->getMockedService();

        $backend = new WebserviceKitResolverBackend($service);
        $query = new Query();

        $this->assertTrue($backend->canResolve($query));
    }

    public function testCanResolveArrayRequirements()
    {
        $service = $this->getMockedService();
        $backend = new WebserviceKitResolverBackend($service);

        $query1 = (new ProgrammesQuery())->setPid('testpid0');
        $query2 = (new ProgrammesQuery())->setPid('testpid1');
        $query3 = (new ProgrammesQuery())->setPid('testpid2');

        $this->assertTrue($backend->canResolve([$query1, $query2, $query3]));
    }

    public function testCanResolveArrayRequirementsFails()
    {
        $service = $this->getMockedService();
        $backend = new WebserviceKitResolverBackend($service);

        $query1 = (new ProgrammesQuery())->setPid('testpid');
        $query2 = 'Uh-oh';
        $query3 = (new ProgrammesQuery())->setPid('testpid2');

        $this->assertFalse($backend->canResolve([$query1, $query2, $query3]));
    }

    public function testCanResolveNonObjectRequirements()
    {
        $service = $this->getMockedService();

        $backend = new WebserviceKitResolverBackend($service);

        $this->assertFalse($backend->canResolve('unknown'));
        $this->assertFalse($backend->canResolve(27));
        $this->assertFalse($backend->canResolve(null));
        $this->assertFalse($backend->canResolve(false));
        $this->assertFalse($backend->canResolve(true));
    }

    /* ------------------ doResolve() tests -------------------- */

    public function testDoResolveSingleQuery()
    {
        $service = $this->getMockedService([
            $this->loadMockedResponse('result1.json')
        ]);

        $backend = new WebserviceKitResolverBackend($service);
        $query = (new ProgrammesQuery())->setPid('testpid');

        $result = $backend->doResolve([$query])[0];

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEquals('b006qpgr', $result->pid);
    }

    public function testDoResolveMultipleQueries()
    {
        $service = $this->getMockedService([
            $this->loadMockedResponse('result1.json'),
            $this->loadMockedResponse('result2.json')
        ]);

        $backend = new WebserviceKitResolverBackend($service);
        $query1 = (new ProgrammesQuery())->setPid('testpid');
        $query2 = (new ProgrammesQuery())->setPid('testpid2');

        $result = $backend->doResolve([$query1, $query2]);

        $this->assertCount(2, $result);

        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertEquals('b006qpgr', $result[0]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[1]);
        $this->assertEquals('b00snr0w', $result[1]->pid);
    }

    public function testDoResolveSingleFailedQuery()
    {
        $service = $this->getMockedService([new Response(500)]);

        $backend = new WebserviceKitResolverBackend($service);
        $query = (new ProgrammesQuery())->setPid('testpid');

        $result = $backend->doResolve([$query]);
        $this->assertCount(1, $result);
        $this->assertFalse($result[0]);
    }

    public function testDoResolveMultipleFailedQueries()
    {
        $service = $this->getMockedService([
            new Response(500),
            new Response(500)
        ]);

        $backend = new WebserviceKitResolverBackend($service);
        $query1 = (new ProgrammesQuery())->setPid('testpid');
        $query2 = (new ProgrammesQuery())->setPid('testpid2');

        $result = $backend->doResolve([$query1, $query2]);

        $this->assertCount(2, $result);
        $this->assertFalse($result[0]);
        $this->assertFalse($result[1]);
    }

    public function testDoResolveSandwichedFailure()
    {
        $service = $this->getMockedService([
            $this->loadMockedResponse('result1.json'),
            new Response(500),
            $this->loadMockedResponse('result2.json'),
        ]);

        $backend = new WebserviceKitResolverBackend($service);
        $query1 = (new ProgrammesQuery())->setPid('testpid');
        $query2 = (new ProgrammesQuery())->setPid('testpid1');
        $query3 = (new ProgrammesQuery())->setPid('testpid2');

        $result = $backend->doResolve([$query1, $query2, $query3]);

        $this->assertCount(3, $result);

        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertEquals('b006qpgr', $result[0]->pid);

        $this->assertFalse($result[1]);

        $this->assertInstanceOf(\stdClass::class, $result[2]);
        $this->assertEquals('b00snr0w', $result[2]->pid);
    }

    public function testDoResolveAllCached()
    {
        $service = $this->getMockedService([new Response(500), new Response(500), new Response(500)]);

        $query1 = (new ProgrammesQuery())->setPid('testpid');
        $query2 = (new ProgrammesQuery())->setPid('testpid1');
        $query3 = (new ProgrammesQuery())->setPid('testpid2');

        // Pre-load the cache:
        /* @var     \Doctrine\Common\Cache\ArrayCache   $cache  */
        $this->preloadCache($service, $query1, [
            'bestBefore'    => 10,
            'storedTime'    => time(),
            'payload'       => ['body' => $this->loadMockedResponse('result1.json'), 'headers' => []]
        ]);
        $this->preloadCache($service, $query2, [
            'bestBefore'    => 10,
            'storedTime'    => time(),
            'payload'       => [
                'body' => $this->loadMockedResponse('result2.json'), 'headers' => []
            ]
        ]);
        $this->preloadCache($service, $query3, [
            'bestBefore'    => 10,
            'storedTime'    => time(),
            'payload'       => ['body' => $this->loadMockedResponse('result3.json'), 'headers' => []]
        ]);


        $backend = new WebserviceKitResolverBackend($service);
        $result = $backend->doResolve([$query1, $query2, $query3]);

        $this->assertCount(3, $result);

        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertEquals('b006qpgr', $result[0]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[1]);
        $this->assertEquals('b00snr0w', $result[1]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[2]);
        $this->assertEquals('b006wq4s', $result[2]->pid);
    }

    public function testDoResolveFirstNeedsFetch()
    {
        $service = $this->getMockedService([
            $this->loadMockedResponse('result1.json'),
        ]);

        $query1 = (new ProgrammesQuery())->setPid('testpid');
        $query2 = (new ProgrammesQuery())->setPid('testpid1');
        $query3 = (new ProgrammesQuery())->setPid('testpid2');

        // Pre-load the cache:
        /* @var     \Doctrine\Common\Cache\ArrayCache   $cache  */
        $this->preloadCache($service, $query2, [
            'bestBefore'    => 10,
            'storedTime'    => time(),
            'payload'       => [
                'body' => $this->loadMockedResponse('result2.json'), 'headers' => []
            ]
        ]);
        $this->preloadCache($service, $query3, [
            'bestBefore'    => 10,
            'storedTime'    => time(),
            'payload'       => ['body' => $this->loadMockedResponse('result3.json'), 'headers' => []]
        ]);


        $backend = new WebserviceKitResolverBackend($service);
        $result = $backend->doResolve([$query1, $query2, $query3]);

        $this->assertCount(3, $result);

        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertEquals('b006qpgr', $result[0]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[1]);
        $this->assertEquals('b00snr0w', $result[1]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[2]);
        $this->assertEquals('b006wq4s', $result[2]->pid);
    }

    public function testDoResolveSecondNeedsFetch()
    {
        $service = $this->getMockedService([
            $this->loadMockedResponse('result2.json'),
        ]);

        $query1 = (new ProgrammesQuery())->setPid('testpid');
        $query2 = (new ProgrammesQuery())->setPid('testpid1');
        $query3 = (new ProgrammesQuery())->setPid('testpid2');

        // Pre-load the cache:
        /* @var     \Doctrine\Common\Cache\ArrayCache   $cache  */
        $this->preloadCache($service, $query1, [
            'bestBefore'    => 10,
            'storedTime'    => time(),
            'payload'       => ['body' => $this->loadMockedResponse('result1.json'), 'headers' => []]
        ]);
        $this->preloadCache($service, $query3, [
            'bestBefore'    => 10,
            'storedTime'    => time(),
            'payload'       => ['body' => $this->loadMockedResponse('result3.json'), 'headers' => []]
        ]);


        $backend = new WebserviceKitResolverBackend($service);
        $result = $backend->doResolve([$query1, $query2, $query3]);

        $this->assertCount(3, $result);

        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertEquals('b006qpgr', $result[0]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[1]);
        $this->assertEquals('b00snr0w', $result[1]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[2]);
        $this->assertEquals('b006wq4s', $result[2]->pid);
    }

    public function testDoResolveThirdNeedsFetch()
    {
        $service = $this->getMockedService([
            $this->loadMockedResponse('result3.json'),
        ]);

        $query1 = (new ProgrammesQuery())->setPid('testpid');
        $query2 = (new ProgrammesQuery())->setPid('testpid1');
        $query3 = (new ProgrammesQuery())->setPid('testpid2');

        // Pre-load the cache:
        /* @var     \Doctrine\Common\Cache\ArrayCache   $cache  */
        $this->preloadCache($service, $query1, [
            'bestBefore'    => 10,
            'storedTime'    => time(),
            'payload'       => ['body' => $this->loadMockedResponse('result1.json'), 'headers' => []]
        ]);
        $this->preloadCache($service, $query2, [
            'bestBefore'    => 10,
            'storedTime'    => time(),
            'payload'       => [
                'body' => $this->loadMockedResponse('result2.json'), 'headers' => []
            ]
        ]);


        $backend = new WebserviceKitResolverBackend($service);
        $result = $backend->doResolve([$query1, $query2, $query3]);

        $this->assertCount(3, $result);

        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertEquals('b006qpgr', $result[0]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[1]);
        $this->assertEquals('b00snr0w', $result[1]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[2]);
        $this->assertEquals('b006wq4s', $result[2]->pid);
    }

    public function testDoResolveSandwich()
    {
        $service = $this->getMockedService([
            $this->loadMockedResponse('result1.json'),
            $this->loadMockedResponse('result3.json'),
        ]);

        $query1 = (new ProgrammesQuery())->setPid('testpid');
        $query2 = (new ProgrammesQuery())->setPid('testpid1');
        $query3 = (new ProgrammesQuery())->setPid('testpid2');

        // Pre-load the cache:
        /* @var     \Doctrine\Common\Cache\ArrayCache   $cache  */
        $this->preloadCache($service, $query2, [
            'bestBefore'    => 10,
            'storedTime'    => time(),
            'payload'       => [
                'body' => $this->loadMockedResponse('result2.json'), 'headers' => []
            ]
        ]);


        $backend = new WebserviceKitResolverBackend($service);
        $result = $backend->doResolve([$query1, $query2, $query3]);

        $this->assertCount(3, $result);

        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertEquals('b006qpgr', $result[0]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[1]);
        $this->assertEquals('b00snr0w', $result[1]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[2]);
        $this->assertEquals('b006wq4s', $result[2]->pid);
    }

    public function testResolveArrayOfArrays()
    {
        $service = $this->getMockedService([
            $this->loadMockedResponse('result1.json'),
            $this->loadMockedResponse('result2.json'),
            $this->loadMockedResponse('result3.json'),
        ]);

        $query1 = (new ProgrammesQuery())->setPid('testpid');
        $query2 = (new ProgrammesQuery())->setPid('testpid1');
        $query3 = (new ProgrammesQuery())->setPid('testpid2');

        $backend = new WebserviceKitResolverBackend($service);
        $result = $backend->doResolve([[$query1, $query2, $query3]]);

        $this->assertCount(1, $result);
        $this->assertCount(3, $result[0]);

        $this->assertInstanceOf(\stdClass::class, $result[0][0]);
        $this->assertEquals('b006qpgr', $result[0][0]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[0][1]);
        $this->assertEquals('b00snr0w', $result[0][1]->pid);

        $this->assertInstanceOf(\stdClass::class, $result[0][2]);
        $this->assertEquals('b006wq4s', $result[0][2]->pid);
    }

    /* ------------- De-duplication tests -------------------- */

    public function testDoResolveDuplicateQueries()
    {
        $service = $this->getMockedService([
            $this->loadMockedResponse('result1.json'),
        ]);

        // Set the monitoring so we can track the number of queries.
        $monitoring = new Monitoring();
        $service->setMonitoring($monitoring);

        $q1 = (new ProgrammesQuery())->setPid('b006qpgr');
        $q2 = (new ProgrammesQuery())->setPid('b006qpgr');

        $backend = new WebserviceKitResolverBackend($service);
        $result = $backend->doResolve([$q1, $q2]);

        $this->assertCount(2, $result);

        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertEquals('b006qpgr', $result[0]->pid);
        $this->assertInstanceOf(\stdClass::class, $result[1]);
        $this->assertEquals('b006qpgr', $result[1]->pid);

        // Ensure that only one request was made:
        $this->assertEquals([
            $q1->getServiceName() => 1
        ], $monitoring->getApisCalled());
    }
}
