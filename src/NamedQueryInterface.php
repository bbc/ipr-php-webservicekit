<?php

namespace BBC\iPlayerRadio\WebserviceKit;

/**
 * Interface NamedQueryInterface
 *
 * Named queries are simply classes which implement a common query that we use
 * regularly in the metadata. Things like "LatestProgrammes" or "PopularClips". By
 * placing them in these queries, we can invisibly swap the implementations should
 * we change the backend service etc.
 *
 * @package     BBC\iPlayerRadio\WebserviceKit
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 */
interface NamedQueryInterface
{
    /**
     * Returns the query to execute against a WebserviceKit\Service instance.
     *
     * @return  \BBC\iPlayerRadio\WebserviceKit\QueryInterface|\BBC\iPlayerRadio\WebserviceKit\QueryInterface[]
     */
    public function query();

    /**
     * Allows the NamedQuery to perform any additional processing on the result
     * before returning it.
     *
     * @param   mixed $results
     * @return  mixed
     */
    public function processResults($results);
}
