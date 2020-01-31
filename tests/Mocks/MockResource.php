<?php


namespace Moofik\LaravelResourceExtenstion\Tests\Mocks;


use Illuminate\Http\Request;
use Moofik\LaravelResourceExtenstion\Extension\RestrictableResource;
use Moofik\LaravelResourceExtenstion\Tests\Utils\HeadsOrTails;

class MockResource extends RestrictableResource
{
    /**
     * @var HeadsOrTails
     */
    private $headsOrTails;

    /**
     * MockResource constructor.
     * @param $resource
     * @param HeadsOrTails $headsOrTails
     */
    public function __construct($resource, HeadsOrTails $headsOrTails)
    {
        parent::__construct($resource);
        $this->headsOrTails = $headsOrTails;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $result = parent::toArray($request);
        $result['coin_side'] = $this->headsOrTails->head();

        return $result;
    }
}