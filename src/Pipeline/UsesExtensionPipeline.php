<?php


namespace Moofik\LaravelResourceExtenstion\Pipeline;


trait UsesExtensionPipeline
{
    /**
     * @param ExtensionPipeline $extensionPipeline
     * @return static
     */
    public function applyPipeline(ExtensionPipeline $extensionPipeline): self
    {
        if (isset($this->policies) && is_array($this->policies)) {
            $this->policies = $extensionPipeline->getResourcePolicies();
        }

        if (isset($this->transformers) && is_array($this->transformers)) {
            $this->transformers = $extensionPipeline->getResourceTransformers();
        }

        return $this;
    }
}