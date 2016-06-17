<?php

namespace BBC\iPlayerRadio\WebserviceKit\Stubs;

use BBC\iPlayerRadio\WebserviceKit\Fixtures\FixtureDefinition;

class ExampleFixture extends FixtureDefinition
{
    public static $implementCalled = false;

    /**
     * This function is where you can define what this service should now do rather than actually fetching data.
     *
     * @return  void
     */
    public function implement()
    {
        self::$implementCalled = true;
    }
}
