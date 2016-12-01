# Named Queries

*Introduced in v1.1.0*

- [Intro](#intro)
- [Writing Named Queries](#writing-named-queries)
    - [query()](#query)
    - [processResults()](#process-results)
- [Using Named Queries](#using-named-queries)

## Intro

WebserviceKit includes the concept of "Named Queries".

A named query is a quick way of defining a common operation that you
wish to abstract away from higher level users of your models. Perhaps
you have common operation whereby you load an item by it's ID:

```php
// Given ItemsQuery is an instanceof BBC\iPlayerRadio\WebserviceKit\QueryInterface

$query = (new ItemsQuery())
    ->setParameter('id', '02374894483')
    ->setParameter('with-comments', 'true')
    ->setParameter('limit', 1);
```

Halfway through your project you realise you need to add another parameter to handle soft-deletes:

```php
$query = (new ItemsQuery())
    ->setParameter('id', '02374894483')
    ->setParameter('with-comments', 'true')
    ->setParameter('limit', 1)
    ->setParameter('deleted', 'false');
```

And now you have to go hunt down every time you're using this query. Annoying.

Named queries allow you to define your operations as *intentions* rather than hard queries. This means you can
change your underlying implementation at any time, and code using those Named Queries doesn't have to care!

## Writing Named Queries

A named query is simply a class that implements the NamedQueryInterface:

```php
interface NamedQueryInterface
{
    /**
     * Returns the query to execute against a WebserviceKit\Service instance.
     *
     * @return  \BBC\iPlayerRadio\WebserviceKit\QueryInterface
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
```

So for the example given in the Intro, we could write the following:

```php
<?php

use BBC\iPlayerRadio\WebserviceKit\NamedQueryInterface;

class FindById implements NamedQueryInterface
{
    protected $id;
    
    public function __construct($id)
    {
        $this->id = $id;
    }
    
    /**
     * Returns the query to execute against a WebserviceKit\Service instance.
     *
     * @return  \BBC\iPlayerRadio\WebserviceKit\QueryInterface|\BBC\iPlayerRadio\WebserviceKit\QueryInterface[]
     */
    public function query()
    {
        return (new ItemsQuery())
             ->setParameter('id', $this->id)
             ->setParameter('with-comments', 'true')
             ->setParameter('limit', 1)
             ->setParameter('deleted', 'false');
    }

    /**
     * Allows the NamedQuery to perform any additional processing on the result
     * before returning it.
     *
     * @param   mixed $results
     * @return  mixed
     */
    public function processResults($results)
    {
        return $results;     
    }
}

```

### query()

The `query()` method is responsible for returning the query (or array of queries) to run.

### processResults()

For certain named queries, there may be an operation you always perform on the result to complete the operation. For
instance, perhaps our ItemsQuery always returns an array of objects, but for our FindById it would be much more
useful if we return the first item.

Therefore, we could write a `processResults()` function that looks like:

```php
/**
 * Allows the NamedQuery to perform any additional processing on the result
 * before returning it.
 *
 * @param   mixed $results
 * @return  mixed
 */
public function processResults($results)
{
    return (array_key_exists(0, $results)? $results[0] : false;     
}
```

Note: this is **in addition to** the transformPayload() on the `QueryInterface` instance! `transformPayload` is run
first, followed by `processResults`.

## Using Named Queries

Named Queries behave exactly like a standard QueryInterface object, you can pass them into `fetch()`:

```php
$q1 = new FindById('id1');
$q2 = new FindById('id2');

list($r1, $r2) = $service->fetch([$q1, $q2]);
```
