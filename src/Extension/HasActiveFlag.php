<?php


namespace Moofik\LaravelResourceExtenstion\Extension;


use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait HasActiveFlag
 * @package Moofik\LaravelResourceExtenstion\Extension
 */
trait HasActiveFlag
{
    /**
     * @var array
     */
    private $activeResource = [];

    /**
     * @var array
     */
    private $inactiveResource = [];

    /**
     * @var bool
     */
    private $attachActiveFlagCalled = false;

    /**
     * @var bool
     */
    private $onlyActiveResourcesCalled = false;

    /**
     * @var bool
     */
    private $onlyInactiveResourcesCalled = false;

    /**
     * @param  Collection  $activeItems
     * @param  Collection  $allItems
     * @return Collection
     */
    public function attachActiveFlag(Collection $activeItems, Collection $allItems)
    {
        $active = $activeItems->intersect($allItems);
        $passive = $allItems->diff($activeItems);

        $active->map(function (Model $item) {
            $item['active'] = true;
        });

        $passive->map(function (Model $item) {
            $item['active'] = false;
        });

        $this->activeResource = $active;
        $this->inactiveResource = $passive;
        $this->attachActiveFlagCalled = true;

        if ($this->onlyActiveResourcesCalled) {
            return $active;
        }

        if ($this->onlyInactiveResourcesCalled) {
            return $passive;
        }

        return $active->merge($passive);
    }

    /**
     * Restricts result collection to resource models with true "active" flag.
     * It can be used either before or after "attachActiveFlag" method - it doesn't affect final result.
     *
     * @return JsonResource|ResourceCollection|HasActiveFlag
     */
    public function onlyActiveResources(): JsonResource
    {
        if ($this->attachActiveFlagCalled) {
            $this->collection = new Collection($this->activeResource);
        } else {
            $this->onlyActiveResourcesCalled = true;
        }


        return $this;
    }

    /**
     * Restricts result collection to resource models with false "active" flag
     * It can be used either before or after "attachActiveFlag" method - it doesn't affect final result.
     *
     * @return JsonResource|ResourceCollection|HasActiveFlag
     */
    public function onlyInactiveResources(): JsonResource
    {
        if ($this->attachActiveFlagCalled) {
            $this->collection = new Collection($this->inactiveResource);
        } else {
            $this->onlyInactiveResourcesCalled = true;
        }


        return $this;
    }
}
