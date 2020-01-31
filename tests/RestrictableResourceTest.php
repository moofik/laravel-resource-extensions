<?php

namespace Moofik\LaravelResourceExtenstion\Tests;

use Illuminate\Http\Request;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\MockExtensionPipeline;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\MockModel;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\MockResource;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\PolicyStub;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\TransformerStub;
use Moofik\LaravelResourceExtenstion\Tests\Utils\HeadsOrTails;
use PHPUnit\Framework\TestCase;

class RestrictableResourceTest extends TestCase
{
    /**
     * @var MockResource
     */
    private $resource;

    /**
     * @var MockModel
     */
    private $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new MockModel();
        $this->model->setAttrs([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'city' => 'New Jersey',
        ]);

        $headsOrTails = new HeadsOrTails();
        $this->resource = new MockResource($this->model, $headsOrTails);
    }

    public function testPolicies()
    {
        $this->model->setHidden(['last_name']);

        $policy_1 = new PolicyStub(['first_name'], []);
        $policy_2 = new PolicyStub([], ['last_name']);

        $result = $this
            ->resource
            ->applyPolicy($policy_1)
            ->applyPolicy($policy_2)
            ->toArray(Request::createFromGlobals());

        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayNotHasKey('first_name', $result);
        $this->assertArrayHasKey('coin_side', $result);
        $this->assertArrayHasKey('city', $result);
    }

    public function testTransformers()
    {
        $transformer_1 = new TransformerStub(function ($resource, array $data) {
            $data['test'] = 'test';
            return $data;
        });

        $transformer_2 = new TransformerStub(function ($resource, array $data) {
            $data['test_2'] = 'test_2';
            return $data;
        });

        $result = $this
            ->resource
            ->applyTransformer($transformer_1)
            ->applyTransformer($transformer_2)
            ->toArray(Request::createFromGlobals());

        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('coin_side', $result);
        $this->assertArrayHasKey('city', $result);
        $this->assertEquals('test', $result['test']);
        $this->assertEquals('test_2', $result['test_2']);
    }

    public function testMultipleTransformersAndPolicies()
    {
        $this->model->setHidden(['last_name']);

        $policy_1 = new PolicyStub(['first_name'], []);
        $policy_2 = new PolicyStub([], ['last_name']);

        $transformer_1 = new TransformerStub(function ($resource, array $data) {
            $data['test'] = 'test';
            return $data;
        });

        $transformer_2 = new TransformerStub(function ($resource, array $data) {
            $data['test_2'] = 'test_2';
            return $data;
        });

        $result = $this
            ->resource
            ->applyPolicy($policy_1)
            ->applyPolicy($policy_2)
            ->applyTransformer($transformer_1)
            ->applyTransformer($transformer_2)
            ->toArray(Request::createFromGlobals());

        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayNotHasKey('first_name', $result);
        $this->assertArrayHasKey('coin_side', $result);
        $this->assertArrayHasKey('city', $result);
        $this->assertEquals('test', $result['test']);
        $this->assertEquals('test_2', $result['test_2']);
    }

    public function testMultipleTransformersAndPoliciesOrderMatters()
    {
        $this->model->setHidden(['last_name']);

        $policy_1 = new PolicyStub(['first_name'], []);
        $policy_2 = new PolicyStub([], ['last_name']);

        $transformer_1 = new TransformerStub(function ($resource, array $data) {
            $data['test'] = 'test';
            return $data;
        });

        $transformer_2 = new TransformerStub(function ($resource, array $data) {
            $data['test'] = 'test_2';
            return $data;
        });

        $result = $this
            ->resource
            ->applyPolicy($policy_1)
            ->applyPolicy($policy_2)
            ->applyTransformer($transformer_1)
            ->applyTransformer($transformer_2)
            ->toArray(Request::createFromGlobals());

        $result_2 = $this
            ->resource
            ->applyTransformer($transformer_2)
            ->applyTransformer($transformer_1)
            ->applyPolicy($policy_1)
            ->applyPolicy($policy_2)
            ->toArray(Request::createFromGlobals());

        $this->assertNotEquals($result, $result_2);
    }

    public function testExtensionPipeline()
    {
        $this->model->setHidden(['last_name']);

        $policy_1 = new PolicyStub(['first_name'], []);
        $policy_2 = new PolicyStub([], ['last_name']);

        $transformer_1 = new TransformerStub(function ($resource, array $data) {
            $data['test'] = 'test';
            return $data;
        });

        $transformer_2 = new TransformerStub(function ($resource, array $data) {
            $data['test_2'] = 'test_2';
            return $data;
        });

        $extensionPipeline = new MockExtensionPipeline();
        $extensionPipeline
            ->addPolicy($policy_1)
            ->addPolicy($policy_2)
            ->addTransformer($transformer_1)
            ->addTransformer($transformer_2);

        $result = $this
            ->resource
            ->applyPipeline($extensionPipeline)
            ->toArray(Request::createFromGlobals());

        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayNotHasKey('first_name', $result);
        $this->assertArrayHasKey('coin_side', $result);
        $this->assertArrayHasKey('city', $result);
        $this->assertEquals('test', $result['test']);
        $this->assertEquals('test_2', $result['test_2']);
    }
}