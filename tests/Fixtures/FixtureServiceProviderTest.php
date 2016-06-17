<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\Fixtures;

use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureServiceProvider;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\Tests\Stubs\ExampleFixture;
use Pimple\Container;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class FixtureServiceProviderTest extends TestCase
{
    use GetMockedService;

    public function testSetGetNamespaces()
    {
        $namespaces = [
            'BBC\\iPlayerRadio\\Fixtures\\',
            'BBC\\Programmes\\Fixtures'
        ];

        $provider = new FixtureServiceProvider();
        $this->assertEquals([], $provider->getNamespaces());
        $this->assertEquals($provider, $provider->setNamespaces($namespaces));
        $this->assertEquals($namespaces, $provider->getNamespaces());
    }

    public function testSetGetDiContainerKey()
    {
        $provider = new FixtureServiceProvider();
        $this->assertEquals('webservicekit', $provider->getDiContainerKey());
        $this->assertEquals($provider, $provider->setDiContainerKey('custom.webservicekit'));
        $this->assertEquals('custom.webservicekit', $provider->getDiContainerKey());
    }

    public function testRegister()
    {
        $originalService = $this->getMockedService();
        $container = new Container();
        $container['webservicekit'] = function () use ($originalService) {
            return $originalService;
        };

        $provider = new FixtureServiceProvider();
        $provider->register($container);

        $this->assertInstanceOf(
            'BBC\\iPlayerRadio\\WebserviceKit\\Fixtures\\FixtureService',
            $container['webservicekit']
        );
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
        $provider->setNamespaces([
            'BBC\\iPlayerRadio\\WebserviceKit\\Tests\\Stubs\\'
        ]);
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
        $provider->setNamespaces([
            'BBC\\iPlayerRadio\\WebserviceKit\\Tests\\Stubs\\'
        ]);
        $app->register($provider);

        ExampleFixture::$implementCalled = false;
        $this->assertFalse(ExampleFixture::$implementCalled);
        $app->handle($request);
        $this->assertFalse(ExampleFixture::$implementCalled);
    }
}
