<?php


namespace Moofik\LaravelResourceExtenstion\Extension;


use Moofik\LaravelResourceExtenstion\Policy\UsesPolicy;
use Moofik\LaravelResourceExtenstion\Transformer\UsesTransformer;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class RestrictableResource extends JsonResource
{
    use UsesPolicy, UsesTransformer;

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
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
        if (empty($this->policy)) {
            return;
        }

        if (is_array($this->resource)) {
            foreach ($this->resource as $singleResource) {
                if ($singleResource instanceof Model) {
                    $singleResource->setHidden($this->policy->getHiddenFields($singleResource));
                    $singleResource->setVisible($this->policy->getVisibleFields($singleResource));
                }
            }
        } elseif ($this->resource instanceof Model) {
            $this->resource->setHidden($this->policy->getHiddenFields($this->resource));
            $this->resource->setVisible($this->policy->getVisibleFields($this->resource));
        }
    }

    /**
     * @param  array  $data
     * @return array
     */
    private function resolveTransformation(array $data): array
    {
        if ($this->transformer) {
            return $this->transformer->transform($this->resource, $data);
        }

        return $data;
    }
}
