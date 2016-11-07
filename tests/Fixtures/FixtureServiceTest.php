<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\Fixtures;

use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Query;
use GuzzleHttp\Psr7\Response;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\Service;
use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureService;

class FixtureServiceTest extends TestCase
{
    use GetMockedService;

    public function testBasicReplacement()
    {
        $service = $this->getMockedService();
        $fixtureService = new FixtureService($service);
        $fixtureService
            ->alterService('unit_tests')
            ->allRequests()
            ->willReturn('{"message": "hello world!"}');

        $query = $this->getMockedQuery();
        $response = $fixtureService->fetch($query);
        $this->assertEquals((object)['message' => 'hello world!'], $response);
    }

    public function testBasicReplacementMiss()
    {
        $service = $this->getMockedService([
            '{"message": "Real Call"}'
        ]);

        $fixtureService = new FixtureService($service);
        $fixtureService
            ->alterService('bbc.nitro') // this service does not match the Query::getServiceName().
            ->allRequests()
            ->willReturn('{"message": "Fixtured Call"}');

        $query = $this->getMockedQuery();
        $response = $fixtureService->fetch($query);
        $this->assertEquals((object)['message' => 'Real Call'], $response);
    }

    public function testSetGetOriginalService()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $this->assertEquals($originalService, $fixtureService->getOriginalService());

        /** @var Service $newOriginal */
        $newOriginal = $this->createMock('BBC\\iPlayerRadio\\WebserviceKit\\Service', [], [], 'Mock2Service', false);
        $this->assertEquals($fixtureService, $fixtureService->setOriginalService($newOriginal));
        $this->assertEquals($newOriginal, $fixtureService->getOriginalService());
    }

    public function testCall()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);
        $fixtureService->thisMethodDoesntExistButItDoesntMatter();
        $this->assertEquals(1, 1);
    }

    /* ---------  Basic return type tests, will check the whole query further down. ------------- */

    public function testAllRequests()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $this->assertEquals($fixtureService, $fixtureService->allRequests());
    }

    public function testQueryHas()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $this->assertEquals($fixtureService, $fixtureService->queryHas('sid', 'bbc_radio_one'));
    }

    public function testQueryHasArray()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $this->assertEquals($fixtureService, $fixtureService->queryHas('sid', ['bbc_radio_one', 'bbc_radio_two']));
    }

    public function testQueryHasNot()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $this->assertEquals($fixtureService, $fixtureService->queryHasNot('sid', 'bbc_radio_one'));
    }

    public function testQueryHasNotArray()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $this->assertEquals($fixtureService, $fixtureService->queryHasNot('sid', ['bbc_radio_one', 'bbc_radio_two']));
    }

    public function testWillReturn()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);
        $this->assertEquals($fixtureService, $fixtureService->willReturn('Body', 200));
    }

    public function testUriHas()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $this->assertEquals($fixtureService, $fixtureService->uriHas('programmes'));
    }

    /* ------------------- Testing Query Application ------------------ */

    public function testAllRequestsApplied()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $fixtureService
            ->alterService('unit_tests')
            ->allRequests()
            ->willReturn('Body', 200);

        $conditions = $fixtureService->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals('*', (string)$conditions[0]['cond']);
        $this->assertEquals(200, $conditions[0]['status']);
        $this->assertEquals('Body', $conditions[0]['body']);
    }

    public function testQueryHasApplied()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $fixtureService
            ->alterService('unit_tests')
            ->queryHas('sid', 'bbc_radio_one')
            ->queryHas('limit', 1)
            ->willReturn('Body', 200);

        $conditions = $fixtureService->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals("sid == bbc_radio_one\nlimit == 1", (string)$conditions[0]['cond']);
        $this->assertEquals(200, $conditions[0]['status']);
        $this->assertEquals('Body', $conditions[0]['body']);
    }

    public function testQueryHasNotApplied()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $fixtureService
            ->alterService('unit_tests')
            ->queryHasNot('sid', 'bbc_radio_one')
            ->queryHasNot('limit', 1)
            ->willReturn('Body', 200);

        $conditions = $fixtureService->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals("sid != bbc_radio_one\nlimit != 1", (string)$conditions[0]['cond']);
        $this->assertEquals(200, $conditions[0]['status']);
        $this->assertEquals('Body', $conditions[0]['body']);
    }

    /* ------------------ Fetching Tests --------------------- */

    public function testFetchStringReturn()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $fixtureService
            ->alterService('unit_tests')
            ->allRequests()
            ->willReturn('Hello', 200);

        $query = $this->getMockedQuery();
        $result = $fixtureService->fetch($query, true);

        $this->assertEquals('Hello', $result);
    }

    public function testFetchCallbackReturn()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $fixtureService
            ->alterService('unit_tests')
            ->allRequests()
            ->willReturn(function (Query $query) {
                return $query->getParameter('sid');
            }, 200);

        $query = $this->getMockedQuery();
        $query->setParameter('sid', 'bbc_radio_two');
        $result = $fixtureService->fetch($query, true);

        $this->assertEquals('bbc_radio_two', $result);
    }

    public function testFetchResponseReturn()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $response = new Response(200, [], 'Response Body');
        $fixtureService
            ->alterService('unit_tests')
            ->allRequests()
            ->willReturn($response);

        $query = $this->getMockedQuery();
        $result = $fixtureService->fetch($query, true);

        $this->assertEquals('Response Body', $result);
    }

    public function testMultiFetch()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $fixtureService
            ->alterService('unit_tests')
            ->allRequests()
            ->willReturn('Hello', 200);

        $queries = [$this->getMockedQuery(), $this->getMockedQuery(), $this->getMockedQuery()];
        $result = $fixtureService->multiFetch($queries, true);

        $this->assertCount(3, $result);
        $this->assertEquals('Hello', $result[0]);
        $this->assertEquals('Hello', $result[1]);
        $this->assertEquals('Hello', $result[2]);
    }

    public function testFetchWithSleep()
    {
        $originalService = $this->getMockedService();
        $fixtureService = new FixtureService($originalService);

        $response = 'Response Body';
        $fixtureService
            ->alterService('unit_tests')
            ->allRequests()
            ->willReturn($response, 200, [], 1);

        $query = $this->getMockedQuery();

        $start = microtime(true);
        $result = $fixtureService->fetch($query, true);
        $end = microtime(true);
        $total = $end - $start;

        $this->assertEquals('Response Body', $result);
        $this->assertGreaterThan(1, $total);
    }

    /**
     * @expectedException           \OutOfBoundsException
     * @expectedExceptionMessage    Test Exception
     */
    public function testFetchServiceThrows()
    {
        $originalService = $this->getMockedService([
            new \OutOfBoundsException('Test Exception')
        ]);
        $fixtureService = new FixtureService($originalService);
        $query = $this->getMockedQuery();
        $fixtureService->fetch($query, true);
    }
}
