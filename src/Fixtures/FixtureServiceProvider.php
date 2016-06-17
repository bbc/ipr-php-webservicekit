<?php

namespace BBC\iPlayerRadio\WebserviceKit\Fixtures;

use BBC\iPlayerRadio\WebserviceKit\DataCollector\FixturesDataCollector;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class FixtureServiceProvider
 *
 * Hooks into Silex to intercept requests and load fixtures should they be needed.
 *
 * @package     BBC\iPlayerRadio\WebserviceKit\Fixtures
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 * @codeCoverageIgnore
 */
class FixtureServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{
    /**
     * @var     array
     */
    protected $fixtureNamespaces = [];

    /**
     * @var     string
     */
    protected $diContainerKey = 'webservicekit';

    /**
     * @var     FixtureService
     */
    protected $fixtureService;

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
     * Returns the key in the DI container where webservicekit is
     *
     * @return  string
     */
    public function getDiContainerKey()
    {
        return $this->diContainerKey;
    }

    /**
     * Sets the key in the DI container where webservicekit is
     *
     * @param   string  $diContainerKey
     * @return  $this
     */
    public function setDiContainerKey($diContainerKey)
    {
        $this->diContainerKey = $diContainerKey;
        return $this;
    }

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple)
    {
        // Swaps out the Service for a FixtureService
        $fixtureService = new FixtureService($pimple[$this->diContainerKey]);
        $this->fixtureService = $fixtureService;
        unset($pimple[$this->diContainerKey]);
        $pimple[$this->diContainerKey] = function () use ($fixtureService) {
            return $fixtureService;
        };
    }

    /**
     * @param Application $app
     */
    public function boot(Application $app)
    {
        $app->before(function (Request $request) use ($app) {
            $definedFailure = $request->query->get('_fixture', false);
            if ($definedFailure) {
                $namespaces = [];
                foreach ($this->fixtureNamespaces as $ns) {
                    $namespaces[] = $ns;
                    $fullClass = $ns.$definedFailure;
                    if (class_exists($fullClass)) {
                        /* @var     \BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureDefinition    $failureClass   */
                        $failureClass = new $fullClass($this->fixtureService, $request);
                        $failureClass->implement();

                        // Tell the data collector about it:
//                        FixturesDataCollector::instance()->fixtureUsed($app, $failureClass);
                    }
                }

//                if (!$failureClass) {
//                    $app['data_collectors.fixtures']->fixtureLoadFailed($definedFailure, $namespaces);
//                }
            }
        });
    }
}
