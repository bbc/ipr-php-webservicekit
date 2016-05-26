<?php

namespace BBC\iPlayerRadio\WebserviceKit\Fixtures;

use Pimple\Container;
use Symfony\Component\HttpFoundation\Request;

abstract class FixtureDefinition
{
    /**
     * @var     Container
     */
    protected $di;

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
     * @var     string
     */
    protected $fixturePath;

    /**
     * @param   Container   $container
     * @param   Request     $request
     * @param   string      $diContainerKey
     */
    public function __construct(Container $container, Request $request, $diContainerKey = 'webservicekit')
    {
        $this->di = $container;
        $this->request = $request;

        $fixtureService = new FixtureService($container[$diContainerKey]);
        $this->fixtureService = $fixtureService;
        unset($this->di[$diContainerKey]);
        $this->di[$diContainerKey] = function () use ($fixtureService) {
            return $fixtureService;
        };
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
     * Loads a file in the current Fixture directory that can be used by Guzzle as a response
     *
     * @param   string  $filename
     * @return  string
     * @throws  \InvalidArgumentException
     * @codeCoverageIgnore
     */
    protected function fixtureFile($filename)
    {
        if (!isset($this->fixturePath)) {
            // This is a bit janky, but we get the filename of the current fixture and work backwards from
            // that to find the fixture directory.
            $reflected = new \ReflectionClass($this);
            $thisFolder = dirname($reflected->getFileName());

            // To allow for nesting of fixtures, we construct the path from a known point:
            $parts = explode('src', $thisFolder);
            $this->fixturePath = $parts[0].'src/Fixture/files/';
        }

        $fullPath = $this->fixturePath.ltrim($filename, '/');
        if (!file_exists($fullPath)) {
            // Ok, try the unit tests path:
            $testsPath = __DIR__.'/../../../tests/fixtures/';

            $fullPath = $testsPath.ltrim($filename, '/');
            if (!file_exists($fullPath)) {
                throw new \InvalidArgumentException($filename.' not found at '.$this->fixturePath.' or '.$testsPath);
            }
        }

        return file_get_contents($fullPath);
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
