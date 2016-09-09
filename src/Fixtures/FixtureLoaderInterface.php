<?php

namespace BBC\iPlayerRadio\WebserviceKit\Fixtures;

use Symfony\Component\HttpFoundation\Request;

interface FixtureLoaderInterface
{
    /**
     * Given a fixture name, will return the FixtureDefinition class that
     * it represents.
     *
     * @param   string              $fixtureName
     * @param   FixtureService      $fixtureService
     * @param   Request             $request
     * @return  FixtureDefinition|false
     */
    public function loadFixtureDefinition($fixtureName, FixtureService $fixtureService, Request $request);
}
