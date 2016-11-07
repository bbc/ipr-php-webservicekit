<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\Fixtures\FixtureLoader;

use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureLoader\NamespaceLoader;
use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use Symfony\Component\HttpFoundation\Request;

class NamespaceLoaderTest extends TestCase
{
    use GetMockedService;

    public function testSetGetNamespaces()
    {
        $namespaces = [
            'BBC\\iPlayerRadio\\Fixtures\\',
            'BBC\\Programmes\\Fixtures'
        ];

        $loader = new NamespaceLoader();
        $this->assertEquals([], $loader->getNamespaces());
        $this->assertEquals($loader, $loader->setNamespaces($namespaces));
        $this->assertEquals($namespaces, $loader->getNamespaces());
    }

    public function testLoadFixtureDefinition()
    {
        $loader = new NamespaceLoader();
        $loader->setNamespaces([
            'BBC\iPlayerRadio\WebserviceKit\Stubs'
        ]);

        $fixture = $loader->loadFixtureDefinition(
            'ExampleFixture',
            new FixtureService($this->getMockedService()),
            new Request()
        );

        $this->assertInstanceOf('BBC\iPlayerRadio\WebserviceKit\Stubs\ExampleFixture', $fixture);
    }

    public function testLoadFixtureDefinitionFails()
    {
        $loader = new NamespaceLoader();
        $loader->setNamespaces([
            'BBC\iPlayerRadio\WebserviceKit\Stubs'
        ]);

        $fixture = $loader->loadFixtureDefinition(
            'UnknownFixture',
            new FixtureService($this->getMockedService()),
            new Request()
        );

        $this->assertFalse($fixture);
    }
}
