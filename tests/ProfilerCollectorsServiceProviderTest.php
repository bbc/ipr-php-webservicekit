<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests;

use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\ProfilerCollectorsServiceProvider;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;

class ProfilerCollectorsServiceProviderTest extends TestCase
{
    public function testRegister()
    {
        $app = new Application();
        $app
            ->register(new TwigServiceProvider())
            ->register(new WebProfilerServiceProvider())
        ;

        $provider = new ProfilerCollectorsServiceProvider();
        $app->register($provider);

        $collectors = $app['data_collectors'];
        $this->assertTrue(array_key_exists('guzzle', $collectors));
        $this->assertInstanceOf(
            'BBC\iPlayerRadio\WebserviceKit\DataCollector\GuzzleDataCollector',
            $collectors['guzzle']()
        );

        $this->assertTrue(array_key_exists('fixtures', $collectors));
        $this->assertInstanceOf(
            'BBC\iPlayerRadio\WebserviceKit\DataCollector\FixturesDataCollector',
            $collectors['fixtures']()
        );

        // Verify that Twig has the path:
        /* @var     \Twig_Loader_Filesystem $loader */
        $loader = $app['twig.loader.filesystem'];
        $this->assertEquals(
            realpath(__DIR__.'/../views'),
            realpath($loader->getPaths('webservicekit')[0])
        );

        $guzzleFound = false;
        $fixturesFound = false;
        foreach ($app['data_collector.templates'] as $profilerTemplate) {
            if ($profilerTemplate[0] === 'guzzle') {
                $guzzleFound = true;
            }

            if ($profilerTemplate[0] === 'fixtures') {
                $fixturesFound = true;
            }
        }

        $this->assertTrue($guzzleFound, 'Guzzle was not included in profiler templates');
        $this->assertTrue($fixturesFound, 'Fixtures were not included in profiler templates');
    }
}
