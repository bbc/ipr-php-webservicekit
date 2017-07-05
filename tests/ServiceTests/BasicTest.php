<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\ServiceTests;

use BBC\iPlayerRadio\Cache\Cache;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Monitoring;
use Doctrine\Common\Cache\ArrayCache;
use GuzzleHttp\Client;

class BasicTest extends TestCase
{
    use GetMockedService;

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

    public function testBeforeQuery()
    {
        $service = $this->getMockedService();
        $this->assertEquals($service, $service->beforeQuery(function ($query) {
            return $query;
        }));
    }
}
