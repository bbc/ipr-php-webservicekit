<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\Fixtures;

use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureService;
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
    protected function getMockFixtureService()
    {
        $service = $this->getMockedService();
        return new FixtureService($service);
    }

    /**
     * @param   FixtureService     $service
     * @return  \BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureDefinition
     */
    protected function getMockDefinition(FixtureService $service)
    {
        return $this->getMockForAbstractClass(
            'BBC\\iPlayerRadio\\WebserviceKit\\Fixtures\\FixtureDefinition',
            [$service, new Request()]
        );
    }

    public function testAlterService()
    {
        $fixtureService = $this->getMockFixtureService();
        $def = $this->getMockDefinition($fixtureService);

        $altered = $def->alterService('bbc.mockservice');

        // Check it's been wrapped.
        $this->assertInstanceOf('BBC\\iPlayerRadio\\WebserviceKit\\Fixtures\\FixtureService', $altered);

        // Check the DI container.
        $this->assertEquals($altered, $fixtureService);
    }

    public function testGetName()
    {
        $fixtureService = $this->getMockFixtureService();
        $def = $this->getMockDefinition($fixtureService);
        $this->assertInternalType('string', $def->getName());
    }
}
