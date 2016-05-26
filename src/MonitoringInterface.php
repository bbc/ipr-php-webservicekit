<?php

namespace BBC\iPlayerRadio\WebserviceKit;

/**
 * Interface MonitoringInterface
 *
 * This interface allows us to send monitoring information to whatever backend you're
 * using to track API calls etc.
 *
 * @package     BBC\iPlayerRadio\WebserviceKit
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 */
interface MonitoringInterface
{
    /**
     * WebserviceKit will tell you how many calls were made to the APIs during a multiFetch.
     * This array takes the form:
     *
     *  [
     *      service name => (int) count of calls
     *  ]
     *
     * @param   array   $callCounts
     * @return  $this
     */
    public function apisCalled(array $callCounts);

    /**
     * @param   string  $serviceName
     * @param   string  $url
     * @param   int     $time
     * @return  $this
     */
    public function slowResponse($serviceName, $url, $time);

    /**
     * @param   string  $serviceName
     * @param   string  $url
     * @param   int     $time
     * @return  $this
     */
    public function responseTime($serviceName, $url, $time);

    /**
     * @param   QueryInterface      $query
     * @param   \Exception          $e
     * @return  $this
     */
    public function onException(QueryInterface $query, \Exception $e);
}
