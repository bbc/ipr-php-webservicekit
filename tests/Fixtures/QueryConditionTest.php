<?php

namespace BBC\iPlayerRadio\WebserviceKit\Fixtures;

use BBC\iPlayerRadio\WebserviceKit\Fixtures\QueryCondition;
use BBC\iPlayerRadio\WebserviceKit\PHPUnit\TestCase;
use BBC\iPlayerRadio\WebserviceKit\Tests\Stubs\Query;

class QueryConditionTest extends TestCase
{
    public function testAny()
    {
        $cond = new QueryCondition();
        $this->assertEquals($cond, $cond->any());
    }

    public function testMatchNoConditions()
    {
        $cond   = new QueryCondition();
        $cond->service('unit_tests');

        $this->assertEquals('', (string)$cond);

        $q      = new Query();
        $q2     = new Query();
        $q2->setParameter('sid', 'bbc_radio_one');

        $this->assertFalse($cond->matches($q));
        $this->assertFalse($cond->matches($q2));
    }

    public function testMatchAny()
    {
        $cond   = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->any();

        $this->assertEquals('*', (string)$cond);

        $q      = new Query();
        $q2     = new Query();
        $q2->setParameter('sid', 'bbc_radio_one');

        $this->assertTrue($cond->matches($q));
        $this->assertTrue($cond->matches($q2));
    }

    public function testMatchSimpleHas()
    {
        $cond   = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->has('sid', 'bbc_radio_one');

        $this->assertEquals('sid == bbc_radio_one', (string)$cond);

        $q      = new Query();
        $q->setParameter('sid', 'bbc_radio_one');
        $this->assertTrue($cond->matches($q));

        $q      = new Query();
        $q->setParameter('sid', 'bbc_radio_two');
        $this->assertFalse($cond->matches($q));

        $q      = new Query();
        $this->assertFalse($cond->matches($q));
    }

    public function testMatchHasWildcard()
    {
        $cond   = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->has('sid', '*');

        $this->assertEquals('sid == *', (string)$cond);

        $q = new Query();
        $q->setParameter('sid', 'bbc_radio_two');
        $this->assertTrue($cond->matches($q));

        $q = new Query();
        $q->setParameter('sid', 'b');
        $this->assertTrue($cond->matches($q));

        $q = new Query();
        $this->assertFalse($cond->matches($q));

        $q = new Query();
        $q->setParameter('not_a_sid', 'bbc_radio_two');
        $this->assertFalse($cond->matches($q));
    }

    public function testMatchHasArrayParameters()
    {
        $cond   = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->has('sid', 'bbc_radio_two');

        $q = new Query();
        $q->setParameter('sid', ['bbc_radio_two', 'bbc_radio_three']);
        $this->assertTrue($cond->matches($q));

        $q = new Query();
        $q->setParameter('sid', ['bbc_radio_one', 'bbc_1xtra']);
        $this->assertFalse($cond->matches($q));
    }

    public function testUrlEquals()
    {
        $cond = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->uriHas('/webservicekit');

        $q = new Query();
        $this->assertTrue($cond->matches($q));

        $q = new Query('unittests');
        $this->assertFalse($cond->matches($q));

        $q = new Query();
        $q->setParameter('sid', 'bbc_radio_one');
        $this->assertTrue($cond->matches($q));
    }

    public function testMatchSimpleHasNot()
    {
        $cond   = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->hasNot('sid', 'bbc_radio_one');

        $this->assertEquals('sid != bbc_radio_one', (string)$cond);

        $q      = new Query();
        $q->setParameter('sid', 'bbc_radio_one');
        $this->assertFalse($cond->matches($q));

        $q      = new Query();
        $q->setParameter('sid', 'bbc_radio_two');
        $this->assertTrue($cond->matches($q));

        $q      = new Query();
        $this->assertTrue($cond->matches($q));
    }

    public function testMatchMultipleHas()
    {
        $cond = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->has('sid', 'bbc_radio_one')
            ->has('limit', 1);

        $this->assertEquals("sid == bbc_radio_one\nlimit == 1", (string)$cond);

        $q = new Query();
        $q
            ->setParameter('sid', 'bbc_radio_one')
            ->setParameter('limit', 1);
        $this->assertTrue($cond->matches($q));

        $q->setParameter('limit', 5);
        $this->assertFalse($cond->matches($q));

        $q
            ->setParameter('sid', 'bbc_radio_two')
            ->setParameter('limit', 1);
        $this->assertFalse($cond->matches($q));
    }

    public function testMatchMultipleHasNot()
    {
        $cond = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->hasNot('sid', 'bbc_radio_one')
            ->hasNot('limit', 1);

        $this->assertEquals("sid != bbc_radio_one\nlimit != 1", (string)$cond);

        $q = new Query();
        $q
            ->setParameter('sid', 'bbc_radio_two')
            ->setParameter('limit', 5);
        $this->assertTrue($cond->matches($q));

        $q->setParameter('limit', 1);
        $this->assertFalse($cond->matches($q));

        $q
            ->setParameter('sid', 'bbc_radio_one')
            ->setParameter('limit', 1);
        $this->assertFalse($cond->matches($q));
    }

    public function testMatchSimpleHasAndHasNot()
    {
        $cond = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->has('sid', 'bbc_radio_one')
            ->hasNot('limit', 1);

        $this->assertEquals("sid == bbc_radio_one\nlimit != 1", (string)$cond);

        $q = new Query();
        $q
            ->setParameter('sid', 'bbc_radio_one')
            ->setParameter('limit', 10);
        $this->assertTrue($cond->matches($q));

        $q = new Query();
        $q
            ->setParameter('sid', 'bbc_radio_two')
            ->setParameter('limit', 10);
        $this->assertFalse($cond->matches($q));

        $q = new Query();
        $q
            ->setParameter('sid', 'bbc_radio_one')
            ->setParameter('limit', 1);
        $this->assertFalse($cond->matches($q));
    }

    public function testMatchHasInArray()
    {
        $cond = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->has('sid', ['bbc_radio_one', 'bbc_radio_two']);

        $this->assertEquals("sid == bbc_radio_one\nsid == bbc_radio_two", (string)$cond);

        $q = new Query();
        $q->setParameter('sid', 'bbc_radio_one');
        $this->assertTrue($cond->matches($q));

        $q = new Query();
        $q->setParameter('sid', 'bbc_radio_two');
        $this->assertTrue($cond->matches($q));

        $q = new Query();
        $q->setParameter('sid', 'bbc_6music');
        $this->assertFalse($cond->matches($q));
    }

    public function testMatchHasNotInArray()
    {
        $cond = new QueryCondition();
        $cond
            ->service('unit_tests')
            ->hasNot('sid', ['bbc_radio_one', 'bbc_radio_two']);

        $this->assertEquals("sid != bbc_radio_one\nsid != bbc_radio_two", (string)$cond);

        $q = new Query();
        $q->setParameter('sid', 'bbc_radio_one');
        $this->assertFalse($cond->matches($q));

        $q = new Query();
        $q->setParameter('sid', 'bbc_radio_two');
        $this->assertFalse($cond->matches($q));

        $q = new Query();
        $q->setParameter('sid', 'bbc_6music');
        $this->assertTrue($cond->matches($q));
    }
}
