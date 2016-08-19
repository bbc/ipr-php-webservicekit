<?php

namespace BBC\iPlayerRadio\WebserviceKit\DataCollector;

use GuzzleHttp\TransferStats;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Class GuzzleDataCollector
 *
 * This data collector can be used for the Symfony profiler to give information
 * about Guzzle requests.
 *
 * @package     BBC\iPlayerRadio\WebserviceKit\DataCollector
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 */
class GuzzleDataCollector extends DataCollector
{
    protected $data = [
        'total_requests'    => 0,
        'total_time'        => 0,
        'requests'          => [],
        'statuses'          => [],
    ];

    /**
     * @var     GuzzleDataCollector
     */
    protected static $instance;

    /**
     * @return GuzzleDataCollector
     */
    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Collects data for the given Request and Response.
     *
     * @param Request    $request   A Request instance
     * @param Response   $response  A Response instance
     * @param \Exception $exception An Exception instance
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // We can now loop through and see if any of these were fixtured:
        $fixtureCollector = FixturesDataCollector::instance();
        foreach ($this->data['requests'] as $url => &$requests) {
            $fixtureInfo = $fixtureCollector->urlFixtureInfo($url);
            foreach ($requests as &$request) {
                $request['fixture'] = $fixtureInfo;
            }
        }
    }

    public function data()
    {
        return $this->data;
    }

    /**
     * Returns the name of the collector.
     *
     * @return string The collector name
     */
    public function getName()
    {
        return 'guzzle';
    }

    /**
     * Adds a guzzle request onto the stack.
     *
     * @param   TransferStats   $stats
     * @return  $this
     */
    public function addRequest(TransferStats $stats)
    {
        $request = $stats->getRequest();
        $response = $stats->getResponse();
        if ($response) {
            $transfer = $stats->getHandlerStats();
            $url = (string)$request->getUri();
            $transfer['requestHeaders'] = $request->getHeaders();
            $transfer['headers'] = $response->getHeaders();
            $transfer['body'] = (string)$response->getBody();

            $headersLower = array_change_key_case($transfer['headers'], CASE_LOWER);
            if (array_key_exists('content-type', $headersLower)
                && stristr($headersLower['content-type'][0], 'application/json') !== false
            ) {
                $transfer['body'] = json_encode(json_decode($transfer['body']), JSON_PRETTY_PRINT);
            }

            $transfer['cacheKey'] = md5($url);
            $this->data['requests'][$url] = [$transfer];
            if (!array_key_exists($response->getStatusCode(), $this->data['statuses'])) {
                $this->data['statuses'][$response->getStatusCode()] = 0;
            }
            $this->data['statuses'][$response->getStatusCode()]++;
            $this->data['total_time'] += $stats->getTransferTime();
            $this->data['total_requests']++;
        }
        return $this;
    }
}
