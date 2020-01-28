## Documentation, Installation, and Usage Instructions

To install the package you need to run next command in your terminal:
``` bash
composer require moofik/laravel-resource-extensions
```
## FEATURES
### 1) Arbitraty resource constructor params (while using ResourceCollection)
Extendable resource collections is used to pass arbitrary params to your resources __construct method, while using ResourceCollection functionality. By default, using ResourceCollection prohibit to have any arbitrary arguments in the corresponded resource, otherwise it will throw an error, because it does not know what arguments it should pass to every instance of corresponded resource.

Image we have resource, corresponded resource collection and controller which uses that resource collection. Here is example how we can use it:
<br><br>Given we have a resource that needs some arguments besides default $resouce argument into __construct:
``` php
class RepairRequestResource extends JsonResource
{
    private $repairOfferRepository;

    public function __construct(
        RepairRequest $resource,
        RepairOffersRepository $repairOfferRepository
    ) {
        parent::__construct($resource);
        $this->repairOfferRepository = $repairOfferRepository;
    }
    
    public function toArray($request)
    {
        $result = parent::toArray($request);
        $result['offers_count'] = $this->repairOfferRepository->countOffersByRequest($this->resource);
        
        return $result;
    }
}
```

<p>
And then we want to use the Collection of this resources in the controller. By default, we are not able to do this, because our RepairRequestResource waits for custom second argument in the __construct method. To do this all we need is our collection to extends

```php
Moofik\LaravelResourceExtenstion\Extension\ExtendableResourceCollection
```
class;
</p>

```php
use Moofik\LaravelResourceExtenstion\Extension\ExtendableResourceCollection;

class RepairRequestResourceCollection extends ExtendableResourceCollection
{
    public function toArray($request)
    {
        /*
         * Maybe some arbitrary but needful actions here
         */ 
        
        return parent::toArray($request);
    }
}
```

<p>
Now arguments we pass into our RepairRequestResourceCollection constructor will automatically be passed to constructor method of every underlying RepairRequestResource resource inside the collection.
</p>

```php
class RepairRequestController
{
    public function repairRequests(RepairOffersRepository $repairOfferRepository)
    {
        $repairRequests = RepairRequest::all();
        
        return new RepairRequestResourceCollection($repairRequests, $repairOfferRepository);
    }
}
```

<strong>Note: if we pass some another arguments to the RepairRequestResourceCollection constructor after the ones we needed, this argumenets could be accesible inside our resource collection via 
```php
$this->args;
```
<strong>

### 2) Resource policies
<p>If you need somehow to force underlying resource model to hide or show some of its fields depending on some sophisticated behaviour we can use resource policies. For example we want to show some resource fields if user has specific role (it may be any condition, you are not somehow restricted in policy logic):
</p>
<p>First, we need to create ResourcePolicy subclass that will define our policy behaviour.</p>

```php
use App\User;
use App\RepairRequest;
use Moofik\LaravelResourceExtenstion\Policy\ResourcePolicy;

class RepairRequestPolicy extends ResourcePolicy
{
    /**
     * @var User
     */
    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * @param RepairRequest $repairRequest
     * @return array
     */
    public function getHiddenFields($repairRequest): array
    {
        if (!$this->user->hasRole(User::USER_ROLE_WORKSHOP)) {
            return ['description', 'city', 'details'];
        }

        return [];
    }

    /**
     * @param RepairRequest $repairRequest
     * @return array
     */
    public function getVisibleFields($repairRequest): array
    {
        return [];
    }
}
```
Now, if we use this policy, it will hide description, city and details fields if user has no "Workshop" role. So, let's use it. To use it we need our resource class extends
```php
\Moofik\LaravelResourceExtenstion\Extension\RestrictableResource
```

After that, our collection obtain two new methods. First (and we need it now) is "applyPolicy" which return policy itself, so that we can use chaining or return it directly from controller method. Let do some code. Here our new resource:

```php
use Moofik\LaravelResourceExtenstion\Extension\RestrictableResource;

class RepairRequest extends RestrictableResource
{
    /**
     * RepairRequest constructor.
     * @param  RepairRequest  $resource
     */
    public function __construct(RepairRequest $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
```

After that we also can use policies with ExtendableResourceCollection subclasses (see 1.) In that case policy will be applied to every underlying resource of RestrictableResource subclass. Below is how we can use it inside controller methods:
```php
public function allRequests(Guard $guard, RepairOffersRepository $repairOfferRepository)
 {
    /** @var User $user */
    $user = $guard->user();
    $repairRequests = RepairRequest::paginate();

    $resource = new RepairRequestResourceCollection($repairRequests, $repairOfferRepository);
    $resourcePolicy = new RepairRequestPolicy($user);

    return $resource->applyPolicy($resourcePolicy);
 }
 
 public function oneRequest(int $id, Guard $guard, RepairOffersRepository $repairOfferRepository)
 {
    /** @var User $user */
    $user = $guard->user();
    $repairRequest = RepairRequest::find($id);

    $resource = new RepairRequestResource($repairRequest, $repairOfferRepository);
    $resourcePolicy = new RepairRequestPolicy($user);

    return $resource->applyPolicy($resourcePolicy);
 }
```
### 3) Resource transformers
<p>If you need somehow to postprocess resulting array of data either for resource or resource collection you can use resource transformers. Given we want to attach "is_offer_made" flag to the resulting resource array based on simple idea "whether user make offer for given repair request": if offer have been made we set flag to true, and false otherwise.
</p>
<p>First, we need to create ResourceTransformer subclass that will define our transformer behaviour.</p>

```php
use App\User;
use App\RepairRequest;
use Moofik\LaravelResourceExtenstion\Transformer\ResourceTransformer;

class RepairRequestWorkshopTransformer extends ResourceTransformer
{
    /**
     * @var RepairRequestOffersRepository
     */
    private $repairRequestOffersRepository;

    /**
     * @var User
     */
    private $user;

    public function __construct(User $user, RepairRequestOffersRepository $repairRequestOffersRepository)
    {
        $this->user = $user;
        $this->repairRequestOffersRepository = $repairRequestOffersRepository;
    }

    /**
     * @param  RepairRequest  $resource
     * @param  array  $data
     * @return array
     */
    public function transform($resource, array $data): array
    {
        if (!$resource instanceof RepairRequest) {
            throw new InvalidArgumentException(sprintf('Invalid argument passed into %s::transform()', __CLASS__));
        }

        $offer = $this->repairRequestOffersRepository->findOneByRepairRequestAndWorkshop($resource, $this->user);
        $data['is_offer_make'] = (null === $offer) ? false : true;

        return $data;
    }
}
```
Now, if we use this transformer, it will postprocess our resource data (it will happen after Laravel calls toArray method of resource). So, let's use it. To use it we need our resource class extends
```php
\Moofik\LaravelResourceExtenstion\Extension\RestrictableResource
```

As we said earlier, our collection obtain two new methods. Method that we will use now is "applyTransformer" which return policy itself, so that we can use chaining or return it directly from controller method. Let do some code. Here our "old friend", the resource, which as earlier extends RestrictableResource in order to use transformers feature:

```php
use Moofik\LaravelResourceExtenstion\Extension\RestrictableResource;

class RepairRequest extends RestrictableResource
{
    /**
     * RepairRequest constructor.
     * @param  RepairRequest  $resource
     */
    public function __construct(RepairRequest $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
```

By the way, we also can use transformers with ExtendableResourceCollection subclasses (see 1.) In that case policy will be applied to every underlying resource of RestrictableResource subclass. Below is how we use it inside controller methods:
```php
public function allRequests(Guard $guard, RepairOffersRepository $repairOfferRepository)
 {
    /** @var User $user */
    $user = $guard->user();
    $repairRequests = RepairRequest::paginate();

    $resource = new RepairRequestResourceCollection($repairRequests, $repairOfferRepository);
    $resourceTransformer = new RepairRequestTransformer($user);

    return $resource->applyTransformer($resourceTransformer);
 }
 
 public function oneRequest(int $id, Guard $guard, RepairOffersRepository $repairOfferRepository)
 {
    /** @var User $user */
    $user = $guard->user();
    $repairRequest = RepairRequest::find($id);

    $resource = new RepairRequestResource($repairRequest, $repairOfferRepository);
    $resourceTransformer = new RepairRequestTransformer($user);

    return $resource->applyTransformer($resourceTransformer);
 }
```

### 4) Error inspection helpers
<p>This small package has a couple of error inspection helper methods to use inside your resources which are convenient in some cases. To use them just add next trait to your class

```php
use Moofik\LaravelResourceExtenstions\Extension\ErrorInspectionHelpers;
```
<br>
The ErrorInspectionHelpers trait methods are listed below:<br><br>
<b>ErrorInspectionHelpers::throwIfResourceIsNot(string $expectedClass)</b> -it will throw InvalidArgumentException if resource is not of class $expectedClass.<br><br>
<b>ErrorInspectionHelpers::throwIfNot($instance, string $expectedClass)</b> -it will throw InvalidArgumentException if passed instance object is not of class $expectedClass.<br><br>
<b>public function throwIfNotAnyOf($instance, array $expectedClasses)</b> -it will throw InvalidArgumentException if passed instance object is not any of classes presented in $expectedClassed array.<br><br>
</p>

### 5) Additional functionality (unstable in beta) - HasActiveFlag trait
<p>It is experimental feature. This trait should be used inside of ResourceCollection and its subclasses. It dynamically attach "active" flag to your items in the collection based on passed arguments, where first argument is collection of somewhat you mean by "active" items, and the second is collection of all items to which we should attach "active" flag. Then, it will be able to retrieve either only "active" or only "passive" items collection from resource. By default, it return all the items (that are presented in second argument) flagged with "active".
</p>

### 6) Security

If you discover any security-related issues, please email [moofik12@gmail.com](mailto:moofik12@gmail.com) instead of using the issue tracker.