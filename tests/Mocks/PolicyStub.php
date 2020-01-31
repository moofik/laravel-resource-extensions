<?php


namespace Moofik\LaravelResourceExtenstion\Tests\Mocks;


use Moofik\LaravelResourceExtenstion\Policy\ResourcePolicy;

class PolicyStub extends ResourcePolicy
{
    /**
     * @var array
     */
    private $hidden;

    /**
     * @var array
     */
    private $visible;

    /**
     * PolicyStub constructor.
     * @param array $hidden
     * @param array $visible
     */
    public function __construct(array $hidden, array $visible)
    {
        $this->hidden = $hidden;
        $this->visible = $visible;
    }

    /**
     * @param mixed $resource
     * @return array
     */
    public function getHiddenFields($resource): array
    {
        return $this->hidden;
    }

    /**
     * @param mixed $resource
     * @return array
     */
    public function getVisibleFields($resource): array
    {
        return $this->visible;
    }
}