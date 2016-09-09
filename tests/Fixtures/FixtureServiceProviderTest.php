<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\Fixtures;

use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureLoader\NamespaceLoader;
use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureServiceProvider;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\Stubs\ExampleFixture;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class FixtureServiceProviderTest extends TestCase
{
    use GetMockedService;

    public function testSetGetDiContainerKey()
    {
        $provider = new FixtureServiceProvider();
        $this->assertEquals('webservicekit', $provider->getDiContainerKey());
        $this->assertEquals($provider, $provider->setDiContainerKey('custom.webservicekit'));
        $this->assertEquals('custom.webservicekit', $provider->getDiContainerKey());
    }

    public function testBootWithFixture()
    {
        $originalService = $this->getMockedService();
        $app = new Application();
        $app->get('/foo', function () {
            return 'foo';
        });
        $app['webservicekit'] = function () use ($originalService) {
            return $originalService;
        };

        $request = Request::create('/foo', 'GET', ['_fixture' => 'ExampleFixture']);

        $provider = new FixtureServiceProvider();
        $provider->addFixtureLoader(
            (new NamespaceLoader())
                ->setNamespaces([
                    'BBC\\iPlayerRadio\\WebserviceKit\\Stubs\\'
                ])
        );
        $app->register($provider);

        ExampleFixture::$implementCalled = false;
        $this->assertFalse(ExampleFixture::$implementCalled);
        $app->handle($request);
        $this->assertTrue(ExampleFixture::$implementCalled);
    }

    public function testBootWithoutFixture()
    {
        $originalService = $this->getMockedService();
        $app = new Application();
        $app->get('/foo', function () {
            return 'foo';
        });
        $app['webservicekit'] = function () use ($originalService) {
            return $originalService;
        };

        $request = Request::create('/foo', 'GET', []); // that empty array is the important bit.

        $provider = new FixtureServiceProvider();
        $provider->addFixtureLoader(
            (new NamespaceLoader())
                ->setNamespaces([
                    'BBC\\iPlayerRadio\\WebserviceKit\\Stubs\\'
                ])
        );
        $app->register($provider);

        ExampleFixture::$implementCalled = false;
        $this->assertFalse(ExampleFixture::$implementCalled);
        $app->handle($request);
        $this->assertFalse(ExampleFixture::$implementCalled);
    }
}
