<?php


namespace Moofik\LaravelResourceExtenstion\Transformer;


trait UsesTransformer
{
    /**
     * @var ResourceTransformer[]
     */
    private $transformers = [];

    /**
     * @param  ResourceTransformer  $transformer
     * @return static
     */
    public function applyTransformer(ResourceTransformer $transformer): self
    {
        $this->transformers[] = $transformer;

        return $this;
    }
}
