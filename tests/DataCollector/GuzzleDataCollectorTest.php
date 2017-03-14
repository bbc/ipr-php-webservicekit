<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\DataCollector;

use BBC\iPlayerRadio\WebserviceKit\DataCollector\FixturesDataCollector;
use BBC\iPlayerRadio\WebserviceKit\DataCollector\GuzzleDataCollector;
use BBC\iPlayerRadio\WebserviceKit\Fixtures\QueryCondition;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetTwig;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Query;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\TransferStats;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GuzzleDataCollectorTest extends TestCase
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
        $collector = new GuzzleDataCollector();
        $this->assertEquals('guzzle', $collector->getName());
    }

    public function testAddingResults()
    {
        $stats = new TransferStats(
            new GuzzleRequest('GET', 'http://example.com/test.php'),
            new GuzzleResponse(200, [], 'Mock Body'),
            5,
            [],
            [
                'http_code' => 200,
                'url' => 'http://example.com/test.php',
                'total_time' => 5,
            ]
        );

        $collector = new GuzzleDataCollector();
        $this->assertEquals(
            $collector,
            $collector->addRequest($stats)
        );

        $data = $collector->data();
        $this->assertEquals([
            'requests' => [
                'http://example.com/test.php' => [
                    [
                        'url' => 'http://example.com/test.php',
                        'total_time' => 5,
                        'http_code' => 200,
                        'body' => 'Mock Body',
                        'headers' => [],
                        'requestHeaders' => [
                            'Host' => [
                                'example.com'
                            ]
                        ]
                    ]
                ]
            ],
            'total_time' => 5,
            'total_requests' => 1,
            'statuses' => [200 => 1]
        ], $data);
    }

    public function testCollect()
    {
        $collector = new GuzzleDataCollector();
        $collector->collect(
            new Request(),
            new Response()
        );
        $this->assertEquals(
            ['requests' => [], 'total_time' => 0, 'total_requests' => 0, 'statuses' => []],
            $collector->data()
        );
    }

    public function testCollectWithFixtures()
    {
        $query = new Query();
        $condition = (new QueryCondition())
            ->has('sid', 'bbc_radio_two');
        $response = new GuzzleResponse(200, [], '{"message": "hello world"}');

        FixturesDataCollector::instance()
            ->conditionMatched($query, $condition, $response);

        $stats = new TransferStats(
            new GuzzleRequest('GET', 'http://localhost/webservicekit'),
            new GuzzleResponse(200, [], 'Mock Body'),
            5,
            [],
            [
                'http_code' => 200,
                'url' => 'http://localhost/webservicekit',
                'total_time' => 5,
            ]
        );

        $collector = new GuzzleDataCollector();
        $this->assertEquals(
            $collector,
            $collector->addRequest($stats)
        );

        $collector->collect(
            new Request(),
            new Response()
        );

        $data = $collector->data();
        $this->assertTrue(array_key_exists('fixture', $data['requests']['http://localhost/webservicekit'][0]));
        $this->assertInternalType('array', $data['requests']['http://localhost/webservicekit'][0]['fixture']);
    }

    /* ---------------- Rendering Tests ----------------- */

    public function testRenderNoRequests()
    {
        $collector = new GuzzleDataCollector();
        $output = $this->twigEnvironment->render('@webservicekit/guzzle.html.twig', [
            'collector' => $collector,
        ]);

        $crawler = new Crawler($output);

        // Check the toolbar:
        $this->assertCount(1, $crawler->filter('.sf-toolbar-icon .sf-toolbar-value:contains("0")'));

        // Check the menu:
        $this->assertCount(1, $crawler->filter('#menu .count span:nth-child(1):contains("0")'));
        $this->assertCount(1, $crawler->filter('#menu .count span:nth-child(2):contains("0.00 ms")'));

        // Check the panel:
        $this->assertCount(1, $crawler->filter('h2:contains("Guzzle HTTP Request")'));
        $this->assertContains('No requests were made', $output);
    }

    public function testRenderSingleRequest()
    {
        $stats = new TransferStats(
            new GuzzleRequest('GET', 'http://example.com/test.php'),
            new GuzzleResponse(200, [], 'Mock Body'),
            5,
            [],
            [
                'http_code' => 200,
                'url' => 'http://example.com/test.php',
                'content_type' => 'application/json',
                'total_time' => 5,
                'connect_time' => 0.202,
                'size_download' => 1024,
                'speed_download' => 24
            ]
        );

        $collector = new GuzzleDataCollector();
        $collector->addRequest($stats);

        $output = $this->twigEnvironment->render('@webservicekit/guzzle.html.twig', [
            'collector' => $collector,
        ]);

        $crawler = new Crawler($output);

        // Check the toolbar:
        $this->assertCount(1, $crawler->filter('.sf-toolbar-icon .sf-toolbar-value:contains("1")'));

        // Check the menu:
        $this->assertCount(1, $crawler->filter('#menu .count span:nth-child(1):contains("1")'));
        $this->assertCount(1, $crawler->filter('#menu .count span:nth-child(2):contains("5000.00 ms")'));

        // Check the panel:
        $this->assertCount(1, $crawler->filter('h2:contains("Guzzle HTTP Request")'));
        $this->assertCount(1, $crawler->filter('h3:contains("Request #1")'));
        $this->assertCount(1, $crawler->filter('#panel .guzzle-request-info'));
        $this->assertCount(1, $crawler->filter('#panel .guzzle-request-additional'));

        // Check the individual elements of the table:
        $this->assertEquals(200, $crawler->filter('.guzzle-request-status')->first()->text());
        $this->assertEquals('application/json', $crawler->filter('.guzzle-request-contenttype')->first()->text());
        $this->assertEquals('5000', $crawler->filter('.guzzle-request-totaltime')->first()->text());
        $this->assertEquals(202, $crawler->filter('.guzzle-request-connecttime')->first()->text());
        $this->assertEquals(1024, $crawler->filter('.guzzle-request-sizedownload')->first()->text());
        $this->assertEquals(24, $crawler->filter('.guzzle-request-speeddownload')->first()->text());
    }

    public function testRenderMultipleRequests()
    {
        $stats = [
            new TransferStats(
                new GuzzleRequest('GET', 'http://example.com/test.php'),
                new GuzzleResponse(200, [], 'Mock Body'),
                5,
                [],
                [
                    'http_code' => 200,
                    'url' => 'http://example.com/test.php',
                    'total_time' => 5,
                ]
            ),
            new TransferStats(
                new GuzzleRequest('GET', 'http://example.com/test2.php'),
                new GuzzleResponse(404, [], 'Mock Body'),
                0.010,
                [],
                [
                    'http_code' => 404,
                    'url' => 'http://example.com/test2.php',
                    'total_time' => 0.010,
                ]
            ),
        ];

        $collector = new GuzzleDataCollector();
        $collector->addRequest($stats[0]);
        $collector->addRequest($stats[1]);

        $output = $this->twigEnvironment->render('@webservicekit/guzzle.html.twig', [
            'collector' => $collector,
        ]);

        $crawler = new Crawler($output);

        // Check the toolbar:
        $this->assertCount(1, $crawler->filter('.sf-toolbar-icon .sf-toolbar-value:contains("2")'));
        $this->assertCount(1, $crawler->filter('#toolbar #toolbar_text .sf-toolbar-status:contains("404")'));
        $this->assertCount(1, $crawler->filter('#toolbar #toolbar_text .sf-toolbar-status:contains("200")'));

        // Check the menu:
        $this->assertCount(1, $crawler->filter('#menu .count span:nth-child(1):contains("2")'));
        $this->assertCount(1, $crawler->filter('#menu .count span:nth-child(2):contains("5010.00 ms")'));

        // Check the panel:
        $this->assertCount(1, $crawler->filter('h2:contains("Guzzle HTTP Request")'));
        $this->assertCount(1, $crawler->filter('h3:contains("Request #1")'));
        $this->assertCount(1, $crawler->filter('h3:contains("Request #2")'));
        $this->assertCount(2, $crawler->filter('.guzzle-request-info'));
        $this->assertCount(2, $crawler->filter('.guzzle-request-additional'));
    }
}
