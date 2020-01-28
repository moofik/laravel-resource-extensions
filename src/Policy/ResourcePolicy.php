<?php


namespace Moofik\LaravelResourceExtenstion\Policy;


abstract class ResourcePolicy
{
    /**
     * @param mixed $resource
     * @return array
     */
    abstract public function getHiddenFields($resource): array;

    /**
     * @param mixed $resource
     * @return array
     */
    abstract public function getVisibleFields($resource): array;
}
