<?php


namespace Moofik\LaravelResourceExtenstions\Extension;


use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Moofik\LaravelResourceExtenstions\Extension\Exception\InvalidArgumentException;

/**
 * Trait ErrorInspectionHelpers
 * @package Moofik\LaravelResourceExtenstions\Extension
 */
trait ErrorInspectionHelpers
{
    /**
     * @param string $expectedClass
     * @throws InvalidArgumentException
     */
    public function throwIfResourceIsNot(string $expectedClass)
    {
        if (!$this->resource instanceof $expectedClass) {
            throw new InvalidArgumentException(
                get_class($this),
                get_class($this->resource),
                $expectedClass
            );
        }
    }

    /**
     * @param mixed $instance
     * @param string $expectedClass
     */
    public function throwIfNot($instance, string $expectedClass)
    {
        if (!$instance instanceof $expectedClass) {
            throw new InvalidArgumentException(
                get_class($this),
                get_class($instance),
                $expectedClass
            );
        }
    }

    /**
     * @param $instance
     * @param array $expectedClasses
     */
    public function throwIfNotAnyOf($instance, array $expectedClasses)
    {
        $expectedClassesList = '';
        $times = 0;

        foreach ($expectedClasses as $expectedClass) {
            try {
                $this->throwIfNot($instance, $expectedClass);
            } catch (\Throwable $exception) {
                $expectedClassesList = ' nor ' . $expectedClass;
                $times++;
            }
        }

        if ($times === count($expectedClasses)) {
            $this->throwIfNot($instance, $expectedClassesList);
        }
    }
}
