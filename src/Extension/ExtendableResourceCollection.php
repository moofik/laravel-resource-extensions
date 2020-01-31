<?php


namespace Moofik\LaravelResourceExtenstion\Extension;


use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Moofik\LaravelResourceExtenstion\Pipeline\UsesExtensionPipeline;
use Moofik\LaravelResourceExtenstion\Policy\UsesPolicy;
use Moofik\LaravelResourceExtenstion\Transformer\UsesTransformer;
use ReflectionClass;
use ReflectionException;

abstract class ExtendableResourceCollection extends ResourceCollection
{
    use UsesPolicy, UsesTransformer, UsesExtensionPipeline;

    /**
     * @var array
     */
    protected $resourceConstructorArguments;

    /**
     * @var array
     */
    protected $args;

    /**
     * ExtendableResourceCollection constructor.
     * @param $resource
     * @param  array  $args
     */
    public function __construct($resource, ...$args)
    {
        $this->args = $args;
        parent::__construct($resource);
    }

    /**
     * @param  mixed  $resource
     * @return AbstractPaginator|Collection|mixed
     */
    public function collectResource($resource)
    {
        if ($resource instanceof MissingValue) {
            return $resource;
        }

        if (is_array($resource)) {
            $resource = new Collection($resource);
        }

        $classToCollect = $this->collects();

        $this->collection = $classToCollect && ! $resource->first() instanceof $classToCollect
            ? $this->mapIntoResourceCollection($classToCollect, $resource)
            : $resource->toBase();

        return $resource instanceof AbstractPaginator
            ? $resource->setCollection($this->collection)
            : $this->collection;
    }

    /**
     * @param  string  $classToCollect
     * @param  Collection|LengthAwarePaginator  $resource
     * @return Collection
     */
    protected function mapIntoResourceCollection(string $classToCollect, $resource): Collection
    {
        try {
            $class = new ReflectionClass($classToCollect);
        } catch (ReflectionException $e) {
            throw new ResourceMappingException(sprintf('Class %s does not exist.', $classToCollect));
        }

        $additionalParametersCount = $class->getConstructor()->getNumberOfParameters() - 1;

        if ($additionalParametersCount > count($this->args)) {
            throw new ResourceMappingException(sprintf('Invalid arguments count passed to %s constructor.', __CLASS__));
        }

        $this->resourceConstructorArguments = array_splice($this->args, 0, $additionalParametersCount);

        return $resource->map(function ($value, $key) use ($classToCollect) {
            $instance = new $classToCollect($value, ...$this->resourceConstructorArguments);

            return $instance;
        });
    }

    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $hasPolices = isset($this->policies) && !empty($this->policies);
        $hasTransformers = isset($this->transformers) && !empty($this->transformers);

        foreach ($this->collection as $instance) {
            if ($hasPolices && ($instance instanceof RestrictableResource)) {
                foreach ($this->policies as $policy) {
                    $instance->applyPolicy($policy);
                }
            }

            if ($hasTransformers && ($instance instanceof RestrictableResource)) {
                foreach ($this->transformers as $transformer) {
                    $instance->applyTransformer($transformer);
                }
            }
        }

        return parent::toArray($request);
    }
}
