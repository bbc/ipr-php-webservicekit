<?php

namespace BBC\iPlayerRadio\WebserviceKit\PHPUnit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Class GetMockedGuzzleClient
 *
 * Returns a guzzle client with mocked responses. Helpful for when we upgrade Guzzle etc.
 *
 * @package     BBC\iPlayerRadio\WebserviceKit\PHPUnit
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 */
trait GetMockedGuzzleClient
{
    /**
     * @param   array   $responses
     * @return  Client
     */
    protected function getMockedGuzzleClient(array $responses = [])
    {
        $responseObjects = [];
        foreach ($responses as $r) {
            if (is_string($r)) {
                $responseObjects[] = new Response(200, [], $r);
            } elseif (is_int($r)) {
                $responseObjects[] = new Response($r);
            } else {
                $responseObjects[] = $r;
            }
        }

        $mock = new MockHandler($responseObjects);
        $handler = HandlerStack::create($mock);
        return new Client(['handler' => $handler]);
    }
}
