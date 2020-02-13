<?php


namespace Moofik\LaravelResourceExtenstion\Extension;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Trait HasActiveFlag
 * @deprecated
 * @package Moofik\LaravelResourceExtenstion\Extension
 */
trait HasActiveFlag
{
    /**
     * @var array
     */
    private $flaggedResult = [];

    /**
     * @var bool
     */
    private $flagCollection = false;

    /**
     * @var bool
     */
    private $onlyTrueFlaggedResources = false;

    /**
     * @var bool
     */
    private $onlyFalseFlaggedResources = false;

    /**
     * @var string
     */
    private $flagName;

    /**
     * @param Collection $activeItems
     * @param Collection $allItems
     * @param string $flagName
     * @return Collection
     */
    public function flagCollection(Collection $activeItems, Collection $allItems, string $flagName): Collection
    {
        $trueFlagged = $activeItems->intersect($allItems);
        $falseFlagged = $allItems->diff($activeItems);
        $this->flagName = $flagName;

        $trueFlagged->each(function (Model $item) use ($flagName) {
            $item[$flagName] = true;
        });

        $falseFlagged->each(function (Model $item) use ($flagName) {
            $item[$flagName] = false;
        });

        $this->flagCollection = true;
        $this->flaggedResult = $trueFlagged->merge($falseFlagged);

        if ($this->onlyTrueFlaggedResources) {
            return $this->flaggedResult->where($flagName, true);
        }

        if ($this->onlyFalseFlaggedResources) {
            return $this->flaggedResult->where($flagName, false);
        }

        return $this->flaggedResult;
    }

    /**
     * Restricts result collection to resource models with true flag.
     * It can be used either before or after "attachActiveFlag" method - it doesn't affect final result.
     *
     * @return static
     */
    public function onlyTrueFlagged(): self
    {
        if ($this->flagCollection) {
            $data = $this->flaggedResult->where($this->flagName, true);
            $this->collection = new Collection($data);
        } else {
            $this->onlyTrueFlaggedResources = true;
        }


        return $this;
    }

    /**
     * Restricts result collection to resource models with false flag
     * It can be used either before or after "flagCollection" method - it doesn't affect final result.
     *
     * @return static
     */
    public function onlyFalseFlagged(): self
    {
        if ($this->flagCollection) {
            $data = $this->flaggedResult->where($this->flagName, false);
            $this->collection = new Collection($data);
        } else {
            $this->onlyFalseFlaggedResources = true;
        }


        return $this;
    }
}
