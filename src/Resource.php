<?php

namespace Ignited\Crud;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** One model exposed on the generic data pages, with its per-resource overrides from `config/crud.php`. */
final class Resource
{
    /** @var list<Field>|null */
    private ?array $fields = null;

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $list  columns the index shows; empty = the first few that fit a table cell
     * @param  list<string>  $hidden  columns never shown, on top of the model's hidden ones
     * @param  array<string, string>  $relations  column => belongs-to method, where the name does not follow `<relation>_id`
     * @param  array{0: string, 1: list<string>}|null  $screen  the dedicated page: route name + the columns that fill its parameters
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $model,
        public readonly string $label,
        public readonly bool $readOnly = false,
        public readonly array $list = [],
        public readonly array $hidden = [],
        public readonly array $relations = [],
        public readonly ?array $screen = null,
        public readonly ?string $note = null,
    ) {}

    /** @param  array<string, mixed>  $config */
    public static function fromConfig(string $slug, array $config): self
    {
        return new self(
            slug: $slug,
            model: $config['model'],
            label: $config['label'] ?? Str::headline($slug),
            readOnly: $config['read_only'] ?? false,
            list: $config['list'] ?? [],
            hidden: $config['hidden'] ?? [],
            relations: $config['relations'] ?? [],
            screen: $config['screen'] ?? null,
            note: $config['note'] ?? null,
        );
    }

    public function newModel(): Model
    {
        return new $this->model;
    }

    public function softDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model), true);
    }

    /** @return Builder<Model> */
    public function query(): Builder
    {
        return $this->model::query();
    }

    public function find(string $id): Model
    {
        $query = $this->softDeletes() ? $this->query()->withTrashed() : $this->query();

        return $query->findOrFail($id);
    }

    /** @return list<Field> */
    public function fields(): array
    {
        return $this->fields ??= FieldInspector::fields($this->newModel(), $this->relations, $this->hidden);
    }

    public function field(string $name): ?Field
    {
        foreach ($this->fields() as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /** @return list<Field> what the detail page shows: everything but secrets */
    public function visibleFields(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => ! $f->isSecret()));
    }

    /** @return list<Field> */
    public function editableFields(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->editable));
    }

    /** @return list<Field> */
    public function listFields(): array
    {
        if ($this->list !== []) {
            return array_values(array_filter(array_map(fn (string $name) => $this->field($name), $this->list), fn (?Field $f) => $f !== null && ! $f->isSecret()));
        }
        $fields = array_filter($this->visibleFields(), fn (Field $f) => ! in_array($f->type, [FieldType::Textarea, FieldType::Json], true) && $f->name !== 'deleted_at');

        return array_slice(array_values($fields), 0, 6);
    }

    /** @return list<string> relations a list or detail page names */
    public function relationNames(): array
    {
        return array_values(array_filter(array_map(fn (Field $f) => $f->relation, $this->visibleFields())));
    }

    /** The dedicated screen for this record, when one exists. */
    public function screenUrl(Model $model): ?string
    {
        if ($this->screen === null) {
            return null;
        }
        [$route, $columns] = $this->screen;

        return route($route, array_map(fn (string $column) => $model->getAttribute($column), $columns));
    }
}
