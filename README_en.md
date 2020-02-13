## Contents
1) [Installation](#installation)
2) [Testing](#testing)
3) [API Resource Collection underlying resource dependency injection](#api-resource-collection-underlying-resource-dependency-injection)
4) [Anonymous extendable resource collections](#anonymous-extendable-resource-collections)
5) [Resource policies](#resource-policies)
6) [Resource transformers](#resource-transformers)
7) [Resource class error inspection helpers](#resource-class-error-inspection-helpers)
8) [Extension pipelines](#extension-pipelines)
9) [Security](#security)

## Installation

To install the package you need to run next command in your terminal:
``` bash
composer require moofik/laravel-resources-extensions
```
### Testing
```shell script
composer test
```

### Features
#### API Resource Collection underlying resource dependency injection
While using API Resource collection (ResourceCollection and its subclasses), extendable resource collections is used to pass arbitrary arguments to ResourceCollection underlying resources ```__construct()``` method. By default, using ResourceCollection prohibit to have any arbitrary arguments in the underlying resources, otherwise it will throw an error (because it does not know what arguments it should pass to every instance of underlying resource).

Image we have resource, corresponded resource collection and controller which uses that resource collection. Here is example how we can use it:
<br><br>Given we have a resource that needs some arguments besides default $resource argument into ```__construct()```:
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

#### Anonymous extendable resource collections
<p>If we don't want to create subclass for our API resource collection, because either we don't want to use custom logic inside ```toArray()``` resource or collection method, or we decided to move all the logic to resource policies and transformers - for these purposes you can use static method ```\Moofik\LaravelResourceExtenstion\Extension\ExtendableResourceCollection::extendableCollection($collection)". It return an instance of ```Moofik\LaravelResourceExtenstion\Extension\ExtendableResourceCollection``` class. As a second argument we can pass class name which will be used for creating items of this resource collection. By default, anonymous extendable collection uses ```Moofik\LaravelResourceExtenstion\Extension\RestrictableResource``` class. Every underlying resource in collection will be created based on this class.
</p>

#### Resource policies
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

After that, our resource will obtain three new methods. First (and we need it now) is "applyPolicy" which return resource itself, so that we can use chaining or return its result directly from controller method - it will be serialized to correct response. 
We also can use policies with ExtendableResourceCollection and its subclasses. In that case policy will be applied to every underlying resource of ```Moofik\LaravelResourceExtenstion\Extension\RestrictableResource``` class or its subclasses. Below is how we can use it inside controller methods:
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

By the way, you MAY use ```Moofik\LaravelResourceExtenstion\Extension\RestrictableResource``` instances as class for your resources if you don't want to place custom logic in resource toArray() method 
and prefer to write it inside of Resource Policies and Resource Transformers (this topics will be discussed in this documentation below)

Notes:
 1) You can apply multiple policies to one API resource, but be careful. Order matters. Order of running policies on API resource is the same as an order of applying that policies to that resource.
 2) <b>You MUST either call ```parent::toArray($request)``` or directly call ```$this->resolvePolicies()```  inside toArray() method of your resource, or create anonymous collection with default second argument (most important thing is that second argument must use ```parent::toArray()``` 
 or directly call ```$this->resolvePolicies()``` inside) to resource policies to work.</b>
#### Resource transformers
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
As we said earlier, our API resource obtain several new methods. Method that we will use now is called ```applyTransformer()``` and it returns resource itself, so that we can use chaining or return it directly from controller method. Here our "old friend", the resource, which as earlier extends RestrictableResource in order to use transformers feature:

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

By the way, we also can use transformers with ```Moofik\LaravelResourceExtenstion\Extension\ExtendableResourceCollection``` subclasses or ```Moofik\LaravelResourceExtenstion\Extension\ExtendableResourceCollection``` itself. In that case transformer will be applied to every underlying resource of ```Moofik\LaravelResourceExtenstion\Extension\RestrictableResource``` subclass. Below is how we use it inside controller methods:
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
 2) <b>You MUST either call ```parent::toArray($request)``` or directly call ```$this->resolveTransformation($data)``` (where data is array that you want to pass to your transformers ```transform($resource, array $data)``` method) inside toArray() method of your resource or create anonymous collection with default second argument
  (most important thing is that second argument must use ```parent::toArray()``` or directly call ```$this->resolveTransformation($data)``` inside. Default ```Moofik\LaravelResourceExtenstion\Extension\RestrictableResource``` class of choice for anonymous collections can handle these things automatically.) to resource transformers to work</b>
#### 4) Resource class error inspection helpers
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

public function throwIfNotAnyOf($instance, array $expectedClasses) // it will throw InvalidArgumentException if passed instance object is not any of classes presented in $expectedClassed array
```
<br>

</p>

#### Extension pipelines
ExtensionPipeline serves as a configuration class that defines which policies and transformers have to be applied to the API Resource or API Resource Collection. 
It can be useful if you are want to apply the same set of policies and transformers to different resources or in multiple places.
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

### Security

If you discover any security-related issues, please email [moofik12@gmail.com](mailto:moofik12@gmail.com) instead of using the issue tracker.