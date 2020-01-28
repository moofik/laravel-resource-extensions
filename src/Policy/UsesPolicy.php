<?php


namespace Moofik\LaravelResourceExtenstion\Policy;


trait UsesPolicy
{
    /**
     * @var ResourcePolicy
     */
    private $policy;

    /**
     * @param  ResourcePolicy  $policy
     *
     * @return static
     */
    public function applyPolicy(ResourcePolicy $policy): self
    {
        $this->policy = $policy;

        return $this;
    }
}
