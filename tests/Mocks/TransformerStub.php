<?php


namespace Moofik\LaravelResourceExtenstion\Tests\Mocks;


use Closure;
use Moofik\LaravelResourceExtenstion\Transformer\ResourceTransformer;

class TransformerStub extends ResourceTransformer
{
    /**
     * @var Closure
     */
    private $closure;

    /**
     * TransformerStub constructor.
     * @param Closure $closure
     */
    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    public function transform($resource, array $data): array
    {
        return $this->closure->call($this, $resource, $data);
    }
}