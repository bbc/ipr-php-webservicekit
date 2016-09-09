<?php

namespace BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureLoader;

use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureDefinition;
use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureLoaderInterface;
use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureService;
use Symfony\Component\HttpFoundation\Request;

class NamespaceLoader implements FixtureLoaderInterface
{
    /**
     * @var     array
     */
    protected $fixtureNamespaces = [];

    /**
     * Sets the namespaces to load Fixtures from. It's up to you to make sure the Autoloader will
     * understand these!
     *
     *      $fixtureSP->setNamespaces([
     *          'Acme\\MyProduct\\Fixtures',
     *          'Acme\\YourProduct\\Fixtures',
     *      ]);
     *
     * @param   array   $namespaces
     * @return  $this
     */
    public function setNamespaces(array $namespaces)
    {
        $this->fixtureNamespaces = $namespaces;
        return $this;
    }

    /**
     * Returns the fixture namespaces the service provider is using
     *
     * @return  array
     */
    public function getNamespaces()
    {
        return $this->fixtureNamespaces;
    }

    /**
     * Given a fixture name, will return the FixtureDefinition class that
     * it represents.
     *
     * @param   string              $fixtureName
     * @param   FixtureService      $fixtureService
     * @param   Request             $request
     * @return  FixtureDefinition|false
     */
    public function loadFixtureDefinition($fixtureName, FixtureService $fixtureService, Request $request)
    {
        $namespaces = [];
        foreach ($this->fixtureNamespaces as $ns) {
            $namespaces[] = $ns;
            $fullClass = $ns.$fixtureName;
            if (class_exists($fullClass)) {
                return new $fullClass($fixtureService, $request);
            }
        }
        return false;
    }
}
