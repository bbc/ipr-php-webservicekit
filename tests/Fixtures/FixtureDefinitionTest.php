<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\Fixtures;

use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use Symfony\Component\HttpFoundation\Request;

class FixtureDefinitionTest extends TestCase
{
    use GetMockedService;

    /**
     * @return  FixtureService
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

    public function testGetSetFixtureService()
    {
        $fixtureService = $this->getMockFixtureService();

        /* @var     \BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureDefinition  $def */
        $def = $this->getMockForAbstractClass(
            'BBC\\iPlayerRadio\\WebserviceKit\\Fixtures\\FixtureDefinition'
        );

        $this->assertNull($def->getFixtureService());
        $this->assertEquals($def, $def->setFixtureService($fixtureService));
        $this->assertEquals($fixtureService, $def->getFixtureService());
    }

    public function testGetSetRequest()
    {
        /* @var     \BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureDefinition  $def */
        $def = $this->getMockForAbstractClass(
            'BBC\\iPlayerRadio\\WebserviceKit\\Fixtures\\FixtureDefinition'
        );

        $request = new Request();

        $this->assertNull($def->getRequest());
        $this->assertEquals($def, $def->setRequest($request));
        $this->assertEquals($request, $def->getRequest());
    }
}
