<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\Fixtures;

use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use Pimple\Container;
use Symfony\Component\HttpFoundation\Request;

class FixtureDefinitionTest extends TestCase
{
    use GetMockedService;

    /**
     * @return  Container
     */
    protected function getMockContainer()
    {
        $service = $this->getMockedService();
        $container = new Container();
        $container['webservicekit'] = function () use ($service) {
            return $service;
        };
        return $container;
    }

    /**
     * @param   Container     $container
     * @return  \BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureDefinition
     */
    protected function getMockDefinition(Container $container)
    {
        return $this->getMockForAbstractClass(
            'BBC\\iPlayerRadio\\WebserviceKit\\Fixtures\\FixtureDefinition',
            [$container, new Request()]
        );
    }

    public function testAlterService()
    {
        $app = $this->getMockContainer();
        $def = $this->getMockDefinition($app);

        $altered = $def->alterService('bbc.mockservice');

        // Check it's been wrapped.
        $this->assertInstanceOf('BBC\\iPlayerRadio\\WebserviceKit\\Fixtures\\FixtureService', $altered);

        // Check the DI container.
        $this->assertEquals($altered, $app['webservicekit']);
    }

    public function testAlterServiceUnknownKey()
    {
        $app = $this->getMockContainer();
        $def = $this->getMockDefinition($app);
        $altered = $def->alterService('unknown');
        $this->assertEquals($altered, $app['webservicekit']);
    }

    public function testGetName()
    {
        $app = $this->getMockContainer();
        $def = $this->getMockDefinition($app);
        $this->assertInternalType('string', $def->getName());
    }
}
