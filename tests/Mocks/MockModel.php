<?php


namespace Moofik\LaravelResourceExtenstion\Tests\Mocks;


use Illuminate\Database\Eloquent\Model;

class MockModel extends Model
{
    /**
     * @param array $attrs
     */
    public function setAttrs(array $attrs): void
    {
        $this->attributes = $attrs;
    }
}