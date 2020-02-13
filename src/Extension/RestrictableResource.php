<?php


namespace Moofik\LaravelResourceExtenstion\Extension;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Moofik\LaravelResourceExtenstion\Pipeline\UsesExtensionPipeline;
use Moofik\LaravelResourceExtenstion\Policy\UsesPolicy;
use Moofik\LaravelResourceExtenstion\Transformer\UsesTransformer;

class RestrictableResource extends JsonResource
{
    use UsesPolicy, UsesTransformer, UsesExtensionPipeline;

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $this->resolvePolicy();

        if (is_null($this->resource)) {
            return [];
        }

        $result = is_array($this->resource)
            ? $this->resource
            : $this->resource->toArray();

        return $this->resolveTransformation($result);
    }

    /**
     * Resolve resource policy
     */
    protected function resolvePolicy()
    {
        if (empty($this->policies)) {
            return;
        }

        foreach ($this->policies as $policy) {
            if (is_array($this->resource)) {
                foreach ($this->resource as $singleResource) {
                    if ($singleResource instanceof Model) {
                        $singleResource->makeHidden($policy->getHiddenFields($singleResource));
                        $singleResource->makeVisible($policy->getVisibleFields($singleResource));
                    }
                }
            } elseif ($this->resource instanceof Model) {
                $this->resource->makeHidden($policy->getHiddenFields($this->resource));
                $this->resource->makeVisible($policy->getVisibleFields($this->resource));
            }
        }
    }

    /**
     * Resolve resource transformations
     *
     * @param array $data
     * @return array
     */
    protected function resolveTransformation(array $data): array
    {
        foreach ($this->transformers as $transformer) {
            $data = $transformer->transform($this->resource, $data);
        }

        return $data;
    }
}
