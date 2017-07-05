<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\ServiceTests;

use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class ErrorStateTest extends TestCase
{
    use GetMockedService;

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

        // Ensure response time is only logged once:
        $this->assertCount(1, $this->monitor->getResponseTimes());
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


        // Ensure response time is only logged once:
        $this->assertCount(1, $this->monitor->getResponseTimes());
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

        // Ensure response time is only logged once:
        $this->assertCount(1, $this->monitor->getResponseTimes());
        $this->assertEquals('unit_tests', $this->monitor->getResponseTimes()[0]['service']);
        $this->assertEquals('http://localhost/unittests', $this->monitor->getResponseTimes()[0]['url']);
        $this->assertInternalType('integer', $this->monitor->getResponseTimes()[0]['time']);

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

        // Ensure response time is only logged once:
        $this->assertCount(1, $this->monitor->getResponseTimes());
        $this->assertEquals('unit_tests', $this->monitor->getResponseTimes()[0]['service']);
        $this->assertEquals('http://localhost/unittests', $this->monitor->getResponseTimes()[0]['url']);
        $this->assertInternalType('integer', $this->monitor->getResponseTimes()[0]['time']);

        $this->assertCount(1, $this->monitor->getExceptions());
        $this->assertContains('cURL error 6', $this->monitor->getExceptions()[0]['exception']->getMessage());
    }
}
