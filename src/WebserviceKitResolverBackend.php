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
        $allQueries = [];
        $flattenMap = [];
        foreach ($requirements as $flattenIDX => $query) {
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

        // De-dupe the queries:
        $seenQueries = [];
        $insertIndex = 0;
        $resultMap = []; // result => queries needing it
        foreach ($allQueries as $requestIdx => $query) {
            $queryIndex = array_search($query, $seenQueries);
            if ($queryIndex === false) {
                $queryIndex = $insertIndex++;
                $seenQueries[] = $query;
            }
            $resultMap[$queryIndex][] = $requestIdx;
        }

        // Request the data:
        $results = $this->service->fetch($seenQueries);

        // Remap the de-duping:
        $flattenedResults = [];
        foreach ($results as $idx => $result) {
            $needingQueries = $resultMap[$idx];
            foreach ($needingQueries as $qIDX) {
                $flattenedResults[$qIDX] = $result;
            }
        }

        ksort($flattenedResults);

        // Un-flatten the result array:
        $mappedResults = [];
        foreach ($flattenedResults as $i => $result) {
            $resultIndex = $flattenMap[$i];
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
