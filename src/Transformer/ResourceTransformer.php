<?php


namespace Moofik\LaravelResourceExtenstion\Transformer;


abstract class ResourceTransformer
{
    /**
     * @param mixed $resource
     * @param  array  $data
     * @return array
     */
    abstract public function transform($resource, array $data): array;
}
