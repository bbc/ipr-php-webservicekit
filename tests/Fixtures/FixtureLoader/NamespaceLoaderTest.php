<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\Fixtures\FixtureLoader;

use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureLoader\NamespaceLoader;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;

class NamespaceLoaderTest extends TestCase
{
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
}
