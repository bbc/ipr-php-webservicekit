<?php

namespace BBC\iPlayerRadio\WebserviceKit\DataCollector;

use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureDefinition;
use BBC\iPlayerRadio\WebserviceKit\Fixtures\QueryCondition;
use BBC\iPlayerRadio\WebserviceKit\QueryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Class FixturesDataCollector
 *
 * Web profiler hook for the Fixtures system.
 *
 * @package     BBC\iPlayerRadio\WebserviceKit\DataCollector
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 */
class FixturesDataCollector extends DataCollector
{
    /**
     * @var     bool|array
     */
    protected $data = ['fixturedRequests' => []];

    /**
     * @var     array
     */
    protected $matchedConditions = [];

    /**
     * @var     bool
     */
    protected $matchedResponse = false;

    /**
     * @var     FixturesDataCollector
     */
    protected static $instance;

    /**
     * @return  FixturesDataCollector
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
        // Doesn't do anything.
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
        return 'fixtures';
    }

    /**
     * Marks a query as being matched.
     *
     * @param   QueryInterface      $query
     * @param   QueryCondition      $condition
     * @param   GuzzleResponse      $response
     * @return  $this
     */
    public function conditionMatched(QueryInterface $query, QueryCondition $condition, GuzzleResponse $response)
    {
        $this->data['fixturedRequests'][] = [
            'url' => $query->getURL(),
            'condition' => (string)$condition,
            'status' => $response->getStatusCode(),
            'body' => (string)$response->getBody()
        ];

        return $this;
    }
}
