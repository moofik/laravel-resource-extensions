<?php


namespace Moofik\LaravelResourceExtenstion\Extension;


use Moofik\LaravelResourceExtenstion\Extension\Exception\InvalidArgumentException;
use Throwable;

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
            if (!isset($this->resource)) {
                $actual = 'NULL';
            } else {
                $actual = get_class($this->resource);
            }

            throw new InvalidArgumentException(
                get_class($this),
                $actual,
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
        if (!isset($this->resource)) {
            $actual = 'NULL';
        } else {
            $actual = get_class($this->resource);
        }

        if (!$instance instanceof $expectedClass) {
            throw new InvalidArgumentException(
                get_class($this),
                $actual,
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
            } catch (Throwable $exception) {
                $expectedClassesList = ' nor ' . $expectedClass;
                $times++;
            }
        }

        if ($times === count($expectedClasses)) {
            $this->throwIfNot($instance, $expectedClassesList);
        }
    }
}
