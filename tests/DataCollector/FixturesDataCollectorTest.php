<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\DataCollector;

use BBC\iPlayerRadio\WebserviceKit\DataCollector\FixturesDataCollector;
use BBC\iPlayerRadio\WebserviceKit\Fixtures\QueryCondition;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetTwig;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Query;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;

class FixturesDataCollectorTest extends TestCase
{
    use GetTwig;

    public function setUp()
    {
        $this->getTwig([]);
        $this->twigLoader->addPath(__DIR__ . '/../../views/', 'webservicekit');
        $this->twigLoader->addPath(__DIR__.'/../../views/mocks/WebProfiler', 'WebProfiler');
    }

    public function testGetName()
    {
        $collector = new FixturesDataCollector();
        $this->assertEquals('fixtures', $collector->getName());
    }

    public function testConditionMatched()
    {
        $query = new Query();
        $condition = (new QueryCondition())
            ->has('sid', 'bbc_radio_two');
        $response = new Response(200, [], '{"message": "hello world"}');

        $collector = new FixturesDataCollector();
        $this->assertEquals(
            $collector,
            $collector->conditionMatched($query, $condition, $response)
        );

        $data = $collector->data();
        $collector->collect(new Request(),new \Symfony\Component\HttpFoundation\Response());

        $this->assertCount(1, $data['fixturedRequests']);

        $fixtured = $data['fixturedRequests'];
        $this->assertEquals(200, $fixtured[0]['status']);
        $this->assertEquals('http://localhost/webservicekit', $fixtured[0]['url']);
        $this->assertEquals('sid == bbc_radio_two', $fixtured[0]['condition']);
        $this->assertEquals('{"message": "hello world"}', $fixtured[0]['body']);
    }

    public function testUrlFixtureInfo()
    {
        $query = new Query();
        $condition = (new QueryCondition())
            ->has('sid', 'bbc_radio_two');
        $response = new Response(200, [], '{"message": "hello world"}');

        $collector = new FixturesDataCollector();
        $this->assertEquals(
            $collector,
            $collector->conditionMatched($query, $condition, $response)
        );

        $collector->collect(new Request(),new \Symfony\Component\HttpFoundation\Response());

        $fixtureInfo = $collector->urlFixtureInfo('http://localhost/webservicekit');
        $this->assertInternalType('array', $fixtureInfo);
        $this->assertEquals(200, $fixtureInfo['status']);

        $fixtureInfo = $collector->urlFixtureInfo('http://unknown');
        $this->assertFalse($fixtureInfo);
    }
}
