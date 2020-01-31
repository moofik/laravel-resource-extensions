<?php


namespace Moofik\LaravelResourceExtenstion\Pipeline;


use Moofik\LaravelResourceExtenstion\Policy\ResourcePolicy;
use Moofik\LaravelResourceExtenstion\Transformer\ResourceTransformer;

abstract class ExtensionPipeline
{
    /**
     * @var ResourcePolicy[]
     */
    private $resourcePolicies = [];

    /**
     * @var ResourceTransformer[]
     */
    private $resourceTransformers = [];

    /**
     * @param ResourcePolicy $resourcePolicy
     * @return ExtensionPipeline
     */
    public function addPolicy(ResourcePolicy $resourcePolicy): ExtensionPipeline
    {
        $this->resourcePolicies[] = $resourcePolicy;

        return $this;
    }

    /**
     * @param ResourceTransformer $resourceTransformer
     * @return ExtensionPipeline
     */
    public function addTransformer(ResourceTransformer $resourceTransformer): ExtensionPipeline
    {
        $this->resourceTransformers[] = $resourceTransformer;

        return $this;
    }

    /**
     * @return ResourcePolicy[]
     */
    public function getResourcePolicies(): array
    {
        return $this->resourcePolicies;
    }

    /**
     * @return ResourceTransformer[]
     */
    public function getResourceTransformers(): array
    {
        return $this->resourceTransformers;
    }
}
