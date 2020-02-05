<?php


namespace Moofik\LaravelResourceExtenstion\Extension\Exception;


use RuntimeException;
use Throwable;

class InvalidArgumentException extends RuntimeException
{
    public function __construct(string $context, string $actual, string $expected, $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            'Invalid argument passed into %s. Passed is %s, but %s expected.',
            $context,
            $actual,
            $expected
        );

        parent::__construct($message, $code, $previous);
    }

}
