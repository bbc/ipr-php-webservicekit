<?php

namespace BBC\iPlayerRadio\WebserviceKit\Fixtures;

use Silex\Application;
use Silex\ServiceProviderInterface;
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
class FixtureServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {

    }

    public function boot(Application $app)
    {
        $app->before(function (Request $request) use ($app) {
            $definedFailure = $request->query->get('_fixture', false);
            if ($definedFailure) {
                // This is a little naughty, but is quicker and easier than inspecting the package
                // classes themselves.
                $composerJSONFile = file_get_contents(__DIR__.'/../../../../composer.json');
                $composerJSON = json_decode($composerJSONFile);
                $failureClass = false;
                $namespaces = [];
                foreach ($composerJSON->autoload->{'psr-4'} as $ns => $path) {
                    $namespaces[] = $ns;
                    $fullClass = $ns.'Fixture\\'.$definedFailure;
                    if (class_exists($fullClass)) {
                        /* @var     \BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureDefinition    $failureClass   */
                        $failureClass = new $fullClass($app, $request);
                        $failureClass->implement();

                        // Tell the data collector about it:
                        $app['data_collectors.fixtures']->fixtureUsed($app, $failureClass);
                    }
                }

                if (!$failureClass) {
                    $app['data_collectors.fixtures']->fixtureLoadFailed($definedFailure, $namespaces);
                }
            }
        });
    }
}
