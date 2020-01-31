<?php

namespace Moofik\LaravelResourceExtenstion\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\MockExtensionPipeline;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\MockModel;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\MockResource;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\MockResourceCollection;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\PolicyStub;
use Moofik\LaravelResourceExtenstion\Tests\Mocks\TransformerStub;
use Moofik\LaravelResourceExtenstion\Tests\Utils\HeadsOrTails;
use PHPUnit\Framework\TestCase;

class ExtendableResourceCollectionTest extends TestCase
{
    /**
     * @var MockResource
     */
    private $resource;

    /**
     * @var MockModel
     */
    private $model;

    /**
     * @var MockModel
     */
    private $model_2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new MockModel();
        $this->model->setAttrs([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->model_2 = new MockModel();
        $this->model_2->setAttrs([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $headsOrTails = new HeadsOrTails();
        $collection = new Collection();
        $collection
            ->add($this->model)
            ->add($this->model_2);

        $this->resource = new MockResourceCollection($collection, $headsOrTails);
    }

    public function testExtendableResourceCollectionPassingArgsToUnderlyingResources()
    {
        $result = $this
            ->resource
            ->toArray(Request::createFromGlobals());

        $this->assertEquals('John', $result[0]['first_name']);
        $this->assertEquals('Doe', $result[0]['last_name']);
        $this->assertArrayHasKey('coin_side', $result[0]);
        $this->assertEquals('Jane', $result[1]['first_name']);
        $this->assertEquals('Doe', $result[1]['last_name']);
        $this->assertArrayHasKey('coin_side', $result[1]);
    }

    public function testExtendableResourceCollectionPolicies()
    {
        $this->model->setHidden(['last_name']);

        $policy_1 = new PolicyStub(['first_name'], []);
        $policy_2 = new PolicyStub([], ['last_name']);

        $result = $this
            ->resource
            ->applyPolicy($policy_1)
            ->applyPolicy($policy_2)
            ->toArray(Request::createFromGlobals());

        $this->assertEquals('Doe', $result[0]['last_name']);
        $this->assertArrayHasKey('coin_side', $result[0]);
        $this->assertEquals('Doe', $result[1]['last_name']);
        $this->assertArrayHasKey('coin_side', $result[1]);
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

        $this->assertEquals('Doe', $result[0]['last_name']);
        $this->assertEquals('test', $result[0]['test']);
        $this->assertEquals('test_2', $result[0]['test_2']);
        $this->assertArrayHasKey('coin_side', $result[0]);
        $this->assertEquals('Doe', $result[1]['last_name']);
        $this->assertEquals('test', $result[1]['test']);
        $this->assertEquals('test_2', $result[1]['test_2']);
        $this->assertArrayHasKey('coin_side', $result[1]);
    }

    public function testMultiplePoliciesAndTransformers()
    {
        $this->model->setHidden(['last_name']);
        $this->model_2->setHidden(['last_name']);

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

        $this->assertEquals('Doe', $result[0]['last_name']);
        $this->assertEquals('test', $result[0]['test']);
        $this->assertEquals('test_2', $result[0]['test_2']);
        $this->assertArrayHasKey('coin_side', $result[0]);
        $this->assertEquals('Doe', $result[0]['last_name']);
        $this->assertEquals('test', $result[0]['test']);
        $this->assertEquals('test_2', $result[0]['test_2']);
        $this->assertArrayHasKey('coin_side', $result[0]);
    }


    public function testMultiplePoliciesAndTransformersOrderMatters()
    {
        $this->model->setHidden(['last_name']);
        $this->model_2->setHidden(['last_name']);

        $policy_1 = new PolicyStub(['first_name'], []);
        $policy_2 = new PolicyStub([], ['first_name']);

        $transformer_1 = new TransformerStub(function ($resource, array $data) {
            $data['test'] = 'test';
            return $data;
        });

        $transformer_2 = new TransformerStub(function ($resource, array $data) {
            $data['test'] = 'test_2';
            return $data;
        });

        $result_1 = $this
            ->resource
            ->applyPolicy($policy_1)
            ->applyPolicy($policy_2)
            ->applyTransformer($transformer_2)
            ->applyTransformer($transformer_1)
            ->toArray(Request::createFromGlobals());

        $result_2 = $this
            ->resource
            ->applyPolicy($policy_2)
            ->applyPolicy($policy_1)
            ->applyTransformer($transformer_2)
            ->applyTransformer($transformer_1)
            ->toArray(Request::createFromGlobals());

        $this->assertNotEquals($result_1, $result_2);
    }

    public function testExtendablePipeline()
    {
        $this->model->setHidden(['last_name']);
        $this->model_2->setHidden(['last_name']);

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

        $this->assertEquals('Doe', $result[0]['last_name']);
        $this->assertEquals('test', $result[0]['test']);
        $this->assertEquals('test_2', $result[0]['test_2']);
        $this->assertArrayHasKey('coin_side', $result[0]);
        $this->assertEquals('Doe', $result[0]['last_name']);
        $this->assertEquals('test', $result[0]['test']);
        $this->assertEquals('test_2', $result[0]['test_2']);
        $this->assertArrayHasKey('coin_side', $result[0]);
    }
}