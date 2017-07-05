<?php

namespace BBC\iPlayerRadio\WebserviceKit;

/**
 * Interface ServiceInterface
 *
 * Common service interface
 *
 * @package     BBC\iPlayerRadio\WebserviceKit
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 * @see         docs/02-service.md
 */
interface ServiceInterface
{
    /**
     * Fetches the service from the URL and hands off to the transformPayload function to do something useful
     * with it. Under the hood, this just uses multiFetch to reduce duplication.
     *
     * @param           QueryInterface|QueryInterface[]  $query
     * @param           bool            $raw        Whether to ignore transformPayload or not.
     * @return          mixed
     * @throws          NoResponseException     When the cache is empty and the request fails.
     */
    public function fetch($query, $raw = false);
}
