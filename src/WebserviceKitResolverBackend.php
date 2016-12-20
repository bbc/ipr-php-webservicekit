<?php

namespace BBC\iPlayerRadio\WebserviceKit;

use BBC\iPlayerRadio\Resolver\ResolverBackend;

/**
 * Class WebserviceKitResolverBackend
 *
 * This class provides a ResolverBackend implementation allowing you to use
 * WebserviceKit requests wit the bbc/ipr-resolver library.
 *
 * @package     BBC\iPlayerRadio\WebserviceKit
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 */
class WebserviceKitResolverBackend implements ResolverBackend
{
    /**
     * @var     Service
     */
    protected $service;

    /**
     * @param   ServiceInterface     $service
     */
    public function __construct(ServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * @return  Service
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param   Service     $service
     * @return  $this
     */
    public function setService(Service $service)
    {
        $this->service = $service;
        return $this;
    }

    /**
     * Returns whether this backend can handle a given Requirement. Requirements
     * can be absolutely anything, so make sure to verify correctly against it.
     *
     * @param   mixed $requirement
     * @return  bool
     */
    public function canResolve($requirement)
    {
        if ($requirement instanceof QueryInterface) {
            return true;
        }

        // If it's an array, loop and check:
        if (is_array($requirement)) {
            foreach ($requirement as $req) {
                if ($req instanceof QueryInterface === false) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Given a list of requirements, perform their resolutions. Requirements can
     * be absolutely anything from strings to full-bore objects.
     *
     * @param   array $requirements
     * @return  array
     */
    public function doResolve(array $requirements)
    {
        // Flatten the queries into a single array:
        list($queries, $flattenMap) = $this->flattenQueries($requirements);

        // De-dupe the queries:
        list($queries, $resultMap) = $this->deDuplicateQueries($queries);

        // Request the data:
        $results = $this->service->fetch($queries);

        // Remap the de-duping:
        $results = $this->reDuplicateResults($results, $resultMap);

        // Un-flatten the result array:
        $results = $this->unFlattenResults($results, $flattenMap);

        return $results;
    }

    /* ------------- Protected doResolve steps ------------------ */

    /**
     * Flattens out the queries into a single list and returns both
     * them and the mapping for them.
     *
     * @param   array   $queries
     * @return  array   [flattened queries, mapping]
     */
    protected function flattenQueries(array $queries)
    {
        $allQueries = [];
        $flattenMap = [];
        foreach ($queries as $flattenIDX => $query) {
            if (is_array($query)) {
                foreach ($query as $q) {
                    $allQueries[] = $q;
                    $flattenMap[] = $flattenIDX;
                }
            } else {
                $allQueries[] = $query;
                $flattenMap[] = $flattenIDX;
            }
        }
        return [$allQueries, $flattenMap];
    }

    /**
     * De-duplicates queries and returns the uniques along with the mapping
     * back to their input format.
     *
     * @param   array    $queries
     * @return  array   [de-duped queries, mapping]
     */
    protected function deDuplicateQueries(array $queries)
    {
        $uniqueQueries = [];
        $insertIndex = 0;
        $resultMap = []; // result => queries needing it
        foreach ($queries as $requestIdx => $query) {
            $queryIndex = array_search($query, $uniqueQueries);
            if ($queryIndex === false) {
                $queryIndex = $insertIndex++;
                $uniqueQueries[] = $query;
            }
            $resultMap[$queryIndex][] = $requestIdx;
        }

        return [$uniqueQueries, $resultMap];
    }

    /**
     * Re-duplicates the results into all of the positions as given by the mapping.
     *
     * @param   array   $results
     * @param   array   $mapping
     * @return  array
     */
    protected function reDuplicateResults(array $results, array $mapping)
    {
        $reDuplicated = [];
        foreach ($results as $idx => $result) {
            $needingQueries = $mapping[$idx];
            foreach ($needingQueries as $qIDX) {
                $reDuplicated[$qIDX] = $result;
            }
        }

        ksort($reDuplicated);
        return $reDuplicated;
    }

    /**
     * Un-flattens the results back into the shape defined in the mapping.
     *
     * @param   array   $results
     * @param   array   $mapping
     * @return  array
     */
    protected function unFlattenResults(array $results, array $mapping)
    {
        $mappedResults = [];
        foreach ($results as $i => $result) {
            $resultIndex = $mapping[$i];
            if (array_key_exists($resultIndex, $mappedResults)) {
                if (is_array($mappedResults[$resultIndex])) {
                    $mappedResults[$resultIndex][] = $result;
                } else {
                    $mappedResults[$resultIndex] = [$mappedResults[$resultIndex], $result];
                }
            } else {
                $mappedResults[$resultIndex] = $result;
            }
        }
        return $mappedResults;
    }
}
