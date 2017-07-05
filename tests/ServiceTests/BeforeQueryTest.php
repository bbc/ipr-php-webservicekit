<?php

namespace BBC\iPlayerRadio\WebserviceKit\Tests\ServiceTests;

use BBC\iPlayerRadio\WebserviceKit\PHPUnit\GetMockedService;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\QueryInterface;
use BBC\iPlayerRadio\WebserviceKit\Stubs\Query;
use BBC\iPlayerRadio\WebserviceKit\Stubs\OtherQuery;

class BeforeQueryTest extends TestCase
{
    use GetMockedService;

    public function testBeforeQueryNoTypehint()
    {
        $service = $this->getMockedService([
            '{"message": "bar"}'
        ]);
        $query = $this->getMockedQuery();

        $beforeCalled = false;
        $service->beforeQuery(function ($query) use (&$beforeCalled) {
            $beforeCalled = true;
            return $query;
        });

        $service->fetch($query);
        $this->assertTrue($beforeCalled);
    }

    public function testBeforeQueryExactTypehint()
    {
        $service = $this->getMockedService([
            '{"message": "foo"}',
        ]);
        $query = $this->getMockedQuery();

        $beforeCalled = false;
        $service->beforeQuery(function (Query $query) use (&$beforeCalled) {
            $beforeCalled = true;
            return $query;
        });

        $service->fetch($query);
        $this->assertTrue($beforeCalled);

        // Doesn't match:

        $service = $this->getMockedService([
            '{"message": "bar"}',
        ]);
        $query = new OtherQuery();

        $beforeCalled = false;
        $service->beforeQuery(function (Query $query) use (&$beforeCalled) {
            $beforeCalled = true;
            return $query;
        });

        $service->fetch($query);
        $this->assertFalse($beforeCalled);
    }

    public function testBeforeQueryParentClass()
    {
        $service = $this->getMockedService([
            '{"message": "foo"}',
        ]);
        $query = $this->getMockedQuery();

        $beforeCalled = false;
        $service->beforeQuery(function (QueryInterface $query) use (&$beforeCalled) {
            $beforeCalled = true;
            return $query;
        });

        $service->fetch($query);
        $this->assertTrue($beforeCalled);
    }

    public function testBeforeQueryMultipleHandlers()
    {
        $service = $this->getMockedService([
            '{"message": "foo"}',
        ]);
        $query = $this->getMockedQuery();

        $beforeCalled1 = false;
        $beforeCalled2 = false;

        $service->beforeQuery(function (Query $query) use (&$beforeCalled1) {
            $beforeCalled1 = true;
            return $query;
        });

        $service->beforeQuery(function ($query) use (&$beforeCalled2) {
            $beforeCalled2 = true;
            return $query;
        });

        $service->fetch($query);
        $this->assertTrue($beforeCalled1);
        $this->assertTrue($beforeCalled2);
    }

    /**
     * @expectedException           \LogicException
     * @expectedExceptionMessage    beforeHandlers must have at least one parameter!
     */
    public function testBeforeHandlerBadMethodSignature()
    {
        $service = $this->getMockedService([
            '{"message": "foo"}',
        ]);
        $query = $this->getMockedQuery();

        $service->beforeQuery(function () {
            // Bad callback, needs a parameter.
        });

        $service->fetch($query);
    }
}
