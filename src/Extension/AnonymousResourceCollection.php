<?php


namespace Moofik\LaravelResourceExtenstion\Extension;


class AnonymousResourceCollection extends ExtendableResourceCollection
{
    /**
     * ExtendableResourceCollection constructor.
     * @param $resource
     * @param array $args
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct($resource, ...$args)
    {
        $this->resource = $resource;
        $this->args = $args;
    }

    /**
     * @param string $class
     * @return $this
     */
    public function setUnderlyingResource(string $class)
    {
        $this->underlyingResourceClass = $class;
        parent::__construct($this->resource, $this->args);

        return $this;
    }
}
