<?php


namespace Moofik\LaravelResourceExtenstion\Policy;


trait UsesPolicy
{
    /**
     * @var ResourcePolicy[]
     */
    private $policies = [];

    /**
     * @param  ResourcePolicy  $policy
     *
     * @return static
     */
    public function applyPolicy(ResourcePolicy $policy): self
    {
        $this->policies[] = $policy;

        return $this;
    }
}
