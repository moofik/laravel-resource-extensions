<?php


namespace Moofik\LaravelResourceExtenstion\Transformer;


trait UsesTransformer
{
    /**
     * @var ResourceTransformer
     */
    private $transformer;

    /**
     * @param  ResourceTransformer  $transformer
     * @return static
     */
    public function applyTransformer(ResourceTransformer $transformer): self
    {
        $this->transformer = $transformer;

        return $this;
    }
}
