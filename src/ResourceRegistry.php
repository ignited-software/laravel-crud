<?php

namespace Ignited\Crud;

use Illuminate\Database\Eloquent\Model;

/**
 * The models the generic data pages may show, opt-in per model in `config/crud.php`: `slug => options`,
 * or just the class of a model that uses {@see Crudable}. A Crudable model's own options sit under the config's.
 */
final class ResourceRegistry
{
    /** @var array<string, resource> */
    private array $resources = [];

    /** @param  array<int|string, string|array<string, mixed>>  $config */
    public function __construct(array $config)
    {
        foreach ($config as $slug => $options) {
            if (is_int($slug) && is_string($options)) {
                [$slug, $options] = [$options::crudSlug(), ['model' => $options]];
            }
            $model = $options['model'];
            if (in_array(Crudable::class, class_uses_recursive($model), true)) {
                $options = [...$model::crudOptions(), ...$options];
            }
            $this->resources[$slug] = Resource::fromConfig($slug, $options);
        }
    }

    /** @return array<string, resource> */
    public function all(): array
    {
        return $this->resources;
    }

    public function find(string $slug): ?Resource
    {
        return $this->resources[$slug] ?? null;
    }

    public function first(): Resource
    {
        return $this->resources[array_key_first($this->resources)];
    }

    public function forModel(Model $model): ?Resource
    {
        foreach ($this->resources as $resource) {
            if ($model instanceof $resource->model) {
                return $resource;
            }
        }

        return null;
    }
}
