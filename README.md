## Documentation, Installation, and Usage Instructions

To install the package you need to run next command in your terminal:
``` bash
composer require moofik/laravel-resources-extensions
```
### Testing
```shell script
composer test
```

### Features
#### 1) Extendable resource collections
While using ResourceCollection and its subclasses, extendable resource collections is used to pass arbitrary arguments to ResourceCollection underlying resources __construct method. By default, using ResourceCollection prohibit to have any arbitrary arguments in the underlying resources, otherwise it will throw an error (because it does not know what arguments it should pass to every instance of underlying resource).

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
Imagine, we want to use the collection of this resource type in the controller. By default, we are not able to do this, because our RepairRequestResource waits for custom second argument in the __construct method, which RepairRequestResourceCollection knows nothing about. To pass that argument all we need is our resource collection class to extends

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
        $result = parent::toArray($request);

        /*
         * Maybe some arbitrary but needful actions on $result here
         */ 
        
        return $result;
    }
}
```

<p>
Now arguments that we pass into our RepairRequestResourceCollection constructor will automatically be passed to constructor method of every underlying RepairRequestResource inside the collection.
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

Note: if we pass some another arguments to the RepairRequestResourceCollection constructor after the ones we needed, this arguments could be accessible inside our resource collection via 
```php
$this->args;
```

#### 2) Resource policies
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
Now, if we use this policy, it will hide description, city and details fields if user has no "Workshop" role. Let's use it. To use it we need our resource class to extend
```php
Moofik\LaravelResourceExtenstion\Extension\RestrictableResource;
```
Here our new API resource:

```php
use App\RepairRequest;
use Moofik\LaravelResourceExtenstion\Extension\RestrictableResource;

class RepairRequestResource extends RestrictableResource
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
        /* If you want policies to work you should use either parent::toArray() or directly call $this->resolvePolicies() */  
        return parent::toArray($request);
    }
}
```

After that, our collection will obtain three new methods. First (and we need it now) is "applyPolicy" which return resource itself, so that we can use chaining or return its result directly from controller method. 
We also can use policies with ExtendableResourceCollection subclasses (see 1.) In that case policy will be applied to every underlying resource of RestrictableResource subclass. Below is how we can use it inside controller methods:
```php
class RepairRequestController
{
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
}
```

Notes:
 1) You can apply multiple policies to one API resource, but be careful. Order matters. Order of running policies on API resource is the same as an order of applying that policies to that resource.
 2) <b>You MUST either call ```parent::toArray($request)``` or directly call ```$this->resolvePolicies()```  inside toArray() method of your resource to resource policies to work</b>
#### 3) Resource transformers
<p>If you need somehow to postprocess resulting array of data either for resource or resource collection you can use resource transformers. Imagine we want to attach "is_offer_made" flag to the resulting resource array, based on simple idea "whether user make offer for given repair request": if offer have been made we set flag to true, and false otherwise.
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
Now, if we use this transformer, it will postprocess our resource data (it will happen after Laravel calls toArray method of resource). Let's use it. To use it we need our resource class to extends
```php
Moofik\LaravelResourceExtenstion\Extension\RestrictableResource;
```
class.
<br>
As we said earlier, our collection obtain several new methods. Method that we will use now is called "applyTransformer" and it returns resource itself, so that we can use chaining or return it directly from controller method. Here our "old friend", the resource, which as earlier extends RestrictableResource in order to use transformers feature:

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

By the way, we also can use transformers with ExtendableResourceCollection subclasses (see 1.) In that case transformer will be applied to every underlying resource of RestrictableResource subclass. Below is how we use it inside controller methods:
```php
class SomeController
{
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
}
```
Notes:
 1) You also can apply multiple transformers to one API resource, but be careful. Order matters. Order of running transformers on API resource is the same as an order of applying that transformers to that resource.
 2) <b>You MUST either call ```parent::toArray($request)``` or directly call ```$this->resolveTransformation($data)``` (where data is array that you want to pass to your transformers ```transform($resource, array $data)``` method) inside toArray() method of your resource to resource transformers to work</b>
#### 4) Error inspection helpers
<p>This small package has a couple of error inspection helper methods to use inside your resources which are convenient in some cases. To use them just add next trait to your class

```php
use Moofik\LaravelResourceExtenstions\Extension\ErrorInspectionHelpers;
```
<br>
The ErrorInspectionHelpers trait methods are listed below:
<p>

```php
public function throwIfResourceIsNot(string $expectedClass) // it will throw InvalidArgumentException if resource is not of class $expectedClass

public function throwIfNot($instance, string $expectedClass) // it will throw InvalidArgumentException if passed instance object is not of class $expectedClass

public function throwIfNotAnyOf(string $expectedClass) // it will throw InvalidArgumentException if passed instance object is not any of classes presented in $expectedClassed array
```
<br>

</p>

#### 5) ExtensionPipeline
ExtensionPipeline serves as a configuration class that defines which policies and transformers have to be applied to the API Resource or API Resource Collection. It can be useful if you are want to apply the same set of policies and transformers to different resources or in multiple places.
Here is the short example:

```php
namespace App\Http\Resources\Pipeline;

use App\User;
use App\Repository\RepairRequestOfferRepository;
use App\Repository\RepairRequestViewerRepository;
use App\Http\Resources\Policy\RepairRequestPolicy;
use App\Http\Resources\Transformer\RepairRequestViewersTransformer;
use App\Http\Resources\Transformer\RepairRequestOffersTransformer;
use Moofik\LaravelResourceExtenstion\Pipeline\ExtensionPipeline;

class RepairRequestExtensionPipeline extends ExtensionPipeline
{
    public function __construct(User $user, RepairRequestOfferRepository $offerRepository, RepairRequestViewerRepository $viewerRepository)
    {
        $this
            ->addPolicy(new RepairRequestPolicy($user, $offerRepository))
            ->addTransformer(new RepairRequestOffersTransformer($user, $offerRepository))
            ->addTransformer(new RepairRequestViewersTransformer($user, $viewerRepository));
    }
}
```

Now we can reuse it in multiple places and with multiple resources:

```php
use App\User;
use App\RepairRequest;
use App\Repository\RepairRequestOfferRepository;
use App\Repository\RepairRequestViewerRepository;
use App\Http\Resources\Pipeline\RepairRequestExtensionPipeline;
use App\Http\Resources\RepairRequestResource;
use App\Http\Resources\RepairRequestResourceCollection;
use Illuminate\Contracts\Auth\Guard;

class SomeController
{
    public function repairRequests(
        Guard $guard,
        RepairRequestOfferRepository $offerRepository,
        RepairRequestViewerRepository $viewerRepository
    ) {
        $repairRequests = RepairRequest::all();
        $pipeline = new RepairRequestExtensionPipeline($guard->user(), $offerRepository, $viewerRepository);
        $resource = new RepairRequestResourceCollection($repairRequests, $offerRepository);

        return $resource->applyPipeline($pipeline);
    }

    public function repairRequest(
        int $id, 
        Guard $guard,
        RepairRequestOfferRepository $offerRepository,
        RepairRequestViewerRepository $viewerRepository
    ) {
        $repairRequest = RepairRequest::find($id);
        $pipeline = new RepairRequestExtensionPipeline($guard->user(), $offerRepository, $viewerRepository);
        $resource = new RepairRequestResource($repairRequest, $offerRepository);

        return $resource->applyPipeline($pipeline);
    }
}
```

#### 6) Additional functionality (unstable in beta) - HasActiveFlag trait
<p>It is experimental feature. This trait should be used inside of ResourceCollection and its subclasses. It dynamically attach "active" flag to your items in the collection based on passed arguments, where first argument is collection of somewhat you mean by "active" items, and the second is collection of all items to which we should attach "active" flag. Then, it will be able to retrieve either only "active" or only "passive" items collection from resource. By default, it return all the items (that are presented in second argument) flagged with "active".
</p>

### Security

If you discover any security-related issues, please email [moofik12@gmail.com](mailto:moofik12@gmail.com) instead of using the issue tracker.