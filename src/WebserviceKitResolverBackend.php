<?php

namespace BBC\iPlayerRadio\WebserviceKit;

use BBC\iPlayerRadio\Resolver\ResolverBackend;

/**
 * Class WebserviceKitResolverBackend
 *
 * This class provides a backend for the BBC\iPlayerRadio\Resolver package,
 * allowing you to use WebserviceKit queries as requirements to the resolver.
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
        if ($requirement instanceof QueryInterface || $requirement instanceof NamedQueryInterface) {
            return true;
        }

        // If it's an array, loop and check:
        if (is_array($requirement)) {
            foreach ($requirement as $req) {
                if ($req instanceof QueryInterface === false && $req instanceof NamedQueryInterface === false) {
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
        return $this->service->fetch($requirements);
    }
}
