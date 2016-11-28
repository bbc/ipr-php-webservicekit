# Resolver Backend

*New in v1.1.0*

If you're a user of the [BBC\iPlayerRadio\Resolver](https://github.com/bbc/ipr-php-resolver) library, you'll
probably want to hook it up to WebserviceKit so that you can use queries as resolutions.

WebserviceKit provides a Resolver Backend to do exactly that:

```php
// Create a service instance:
$service = new \BBC\iPlayerRadio\WebserviceKit\Service(
    new \GuzzleHttp\Client(),
    new \BBC\iPlayerRadio\Cache\Cache(new \Doctrine\Common\Cache\RedisCache())
);

$resolver = new \BBC\iPlayerRadio\Resolver\Resolver();

// Register the backend:
$resolver->addBackend(
    new \BBC\iPlayerRadio\WebserviceKit\WebserviceKitResolverBackend($service)
);
```

You can now yield `QueryInterface` or `NamedQueryInterface` instances from your `requires` blocks:

```php
class ItemQuery extends \BBC\iPlayerRadio\WebserviceKit\Query
{
    // ...
}

class Article implements \BBC\iPlayerRadio\Resolver\HasRequirements
{    
    protected $id;
    
    public function __construct($id)
    {
        $this->id = $id;
    }
    
    public function requires(array $flags = [])
    {
        $this->data = (yield new ItemQuery($this->id));  
    }   
}

$article = new Article('my-id');
$resolver->resolve($article);

```
