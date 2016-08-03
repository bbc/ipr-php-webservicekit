<?php

namespace BBC\iPlayerRadio\WebserviceKit\Fixtures;

use BBC\iPlayerRadio\WebserviceKit\QueryInterface;

/**
 * Class QueryCondition
 *
 * Defines a condition for Queries to match to allow for a fixture to be used.
 *
 * @package     BBC\iPlayerRadio\WebserviceKit\Fixtures
 * @author      Alex Gisby <alex.gisby@bbc.co.uk>
 * @copyright   BBC
 */
class QueryCondition
{
    const OP_EQ = '==';
    const OP_NEQ = '!=';
    const URL_EQ = '?=';

    /**
     * @var     string
     */
    protected $fixtureDefinition = null;

    /**
     * @var     string
     */
    protected $service = false;

    /**
     * @var     bool
     */
    protected $any = false;

    /**
     * @var     array
     */
    protected $conditions = [];

    /**
     * @var     int
     */
    protected $responseStatus = 200;

    /**
     * @var     mixed
     */
    protected $response = '';

    public function __construct($fixtureDefinition = null)
    {
        $this->fixtureDefinition = $fixtureDefinition;
    }

    /**
     * @return  string
     */
    public function getFixtureDefinition()
    {
        return $this->fixtureDefinition;
    }

    /**
     * Tells the query which service name this query should match
     *
     * @param   string
     * @return  $this
     */
    public function service($service)
    {
        $this->service = $service;
        return $this;
    }

    /**
     * Returns the service this is acting upon
     *
     * @return  string
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Makes this query match anything passed to it
     *
     * @return  $this
     */
    public function any()
    {
        $this->any = true;
        return $this;
    }

    /**
     * Adds an 'equals' condition to the query.
     *
     * @param   string              $key
     * @param   string|int|array    $value
     * @return  $this
     */
    public function has($key, $value)
    {
        $this->conditions[] = [
            'key' => $key,
            'op' => self::OP_EQ,
            'value' => $value
        ];
        return $this;
    }

    /**
     * Adds a not equals condition to the query.
     *
     * @param   string              $key
     * @param   string|int|array    $value
     * @return  $this
     */
    public function hasNot($key, $value)
    {
        $this->conditions[] = [
            'key' => $key,
            'op' => self::OP_NEQ,
            'value' => $value
        ];
        return $this;
    }

    /**
     * @param   $value
     * @return  $this
     */
    public function uriHas($value)
    {
        $this->conditions[] = [
            'op' => self::URL_EQ,
            'value' => $value,
            'key' => 'URI'
        ];
        return $this;
    }

    /**
     * Checks to see if the provided query matches the conditions present in this builder.
     *
     * @param   QueryInterface   $query
     * @return  bool
     */
    public function matches(QueryInterface $query)
    {
        if ($this->service !== $query->getServiceName()) {
            return false;
        }

        if ($this->any) {
            return true;
        }

        if (!$this->any && !$this->conditions) {
            return false;
        }

        // Loop through and verify conditions:
        $matched = true;
        foreach ($this->conditions as $cond) {
            // Remember, we're checking if it DOESN'T match, so the operators are the opposite
            // to what you think they should be.
            switch ($cond['op']) {
                case self::URL_EQ:
                    $parts = explode('?', $query->getURL());
                    if (!preg_match('/'.preg_quote($cond['value'], '/').'/i', $parts[0])) {
                        $matched = false;
                    }
                    break;
                case self::OP_EQ:
                    if ($cond['value'] == '*') {
                        if ($query->getParameter($cond['key'], false) === false) {
                            $matched = false;
                        }
                    } elseif (is_array($query->getParameter($cond['key']))) {
                        //
                        // This elseif is used for when the NamedQuery / Query itself has a parameter which is
                        // an array.
                        //
                        if (!in_array($cond['value'], $query->getParameter($cond['key']))) {
                            $matched = false;
                        }
                    } elseif (is_array($cond['value'])) {
                        //
                        // This elseif is used for when you want to match multiple payloads against an array
                        // of possible parameters
                        //
                        if (!in_array($query->getParameter($cond['key']), $cond['value'])) {
                            $matched = false;
                        }
                    } else {
                        if (!($query->getParameter($cond['key'], false) == $cond['value'])) {
                            $matched = false;
                        }
                    }
                    break;

                case self::OP_NEQ:
                    if (is_array($cond['value'])) {
                        if (in_array($query->getParameter($cond['key']), $cond['value'])) {
                            $matched = false;
                        }
                    } else {
                        if (!($query->getParameter($cond['key'], false) != $cond['value'])) {
                            $matched = false;
                        }
                    }
                    break;
            }
        }

        return $matched;
    }

    /**
     * Returns a string formatted to display what the conditions are. Useful for debugging.
     *
     * @return  string
     */
    public function __toString()
    {
        if ($this->any) {
            return '*';
        }

        $parts = [];
        foreach ($this->conditions as $cond) {
            $value = $cond['value'];
            if (!is_array($value)) {
                $value = [$value];
            }
            foreach ($value as $v) {
                $parts[] = $cond['key'].' '.$cond['op'].' '.$v;
            }
        }
        return implode(PHP_EOL, $parts);
    }
}
