<?php

namespace BBC\iPlayerRadio\WebserviceKit\Fixtures;

use Pimple\Container;
use Symfony\Component\HttpFoundation\Request;

abstract class FixtureDefinition
{
    /**
     * @var     string
     */
    protected $diContainerKey = 'webservicekit';

    /**
     * @var     FixtureService
     */
    protected $fixtureService;

    /**
     * @var     Request
     */
    protected $request;

    /**
     * @param   FixtureService      $service
     * @param   Request             $request
     */
    public function __construct(FixtureService $service, Request $request)
    {
        $this->fixtureService = $service;
        $this->request = $request;
    }

    /**
     * Registers a service as broken and returns an object on which you can define how it is broken.
     *
     * @param   string  $service    Service name from the DI container to break.
     * @return  FixtureService
     */
    public function alterService($service)
    {
        // All this does is kick off the process of altering the given service:
        return $this->fixtureService->alterService($service, get_class($this));
    }

    /**
     * Returns the name of this fixture. Basically the fully qualified class name.
     *
     * @return  string
     */
    public function getName()
    {
        return get_class($this);
    }

    /**
     * This function is where you can define what this service should now do rather than actually fetching data.
     *
     * @return  void
     */
    abstract public function implement();
}
