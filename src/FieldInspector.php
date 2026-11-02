<?php

namespace Ignited\Crud;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Throwable;

/** Reads a model's table columns and casts into fields; nothing here names a model. */
final class FieldInspector
{
    /** Shown on every page, never written through a form. */
    private const MANAGED = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * @param  array<string, string>  $relations  column => relation method, where the name does not follow `<relation>_id`
     * @param  list<string>  $hidden  extra columns never shown, on top of the model's own hidden ones
     * @return list<Field>
     */
    public static function fields(Model $model, array $relations = [], array $hidden = []): array
    {
        $casts = $model->getCasts();
        $fillable = $model->getFillable();
        $fields = [];

        foreach (Schema::getColumns($model->getTable()) as $column) {
            $name = $column['name'];
            $cast = is_string($casts[$name] ?? null) ? $casts[$name] : '';
            $secret = in_array($name, $model->getHidden(), true) || str_starts_with($cast, 'encrypted') || $cast === 'hashed';
            $editable = in_array($name, $fillable, true) && $name !== $model->getKeyName() && ! in_array($name, self::MANAGED, true) && empty($column['generation']);

            if (in_array($name, $hidden, true) || ($secret && ! $editable)) {
                continue;
            }

            $relation = self::relation($model, $name, $relations[$name] ?? null);
            $type = match (true) {
                $secret => FieldType::Secret,
                $relation !== null => FieldType::Relation,
                $cast !== '' && enum_exists($cast) => FieldType::Select,
                in_array($cast, ['array', 'json', 'object', 'collection'], true) || str_contains($cast, 'AsArrayObject') || str_contains($cast, 'AsCollection') || $column['type_name'] === 'json' => FieldType::Json,
                $cast === 'boolean' || $cast === 'bool' || $column['type'] === 'tinyint(1)' => FieldType::Switch,
                in_array($cast, ['date', 'immutable_date'], true) || $column['type_name'] === 'date' => FieldType::Date,
                str_contains($cast, 'datetime') || $cast === 'timestamp' || in_array($column['type_name'], ['datetime', 'timestamp'], true) => FieldType::DateTime,
                in_array($cast, ['integer', 'int'], true) || in_array($column['type_name'], ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint'], true) => FieldType::Integer,
                in_array($cast, ['float', 'double', 'real'], true) || str_starts_with($cast, 'decimal') || in_array($column['type_name'], ['decimal', 'float', 'double'], true) => FieldType::Number,
                in_array($column['type_name'], ['text', 'mediumtext', 'longtext', 'tinytext'], true) => FieldType::Textarea,
                default => FieldType::Text,
            };

            $fields[] = new Field(
                name: $name,
                type: $type,
                editable: $editable,
                nullable: (bool) $column['nullable'],
                hasDefault: $column['default'] !== null || (bool) ($column['auto_increment'] ?? false),
                maxLength: preg_match('/^(?:var)?char\((\d+)\)$/', (string) $column['type'], $m) ? (int) $m[1] : null,
                options: $type === FieldType::Select ? self::enumOptions($cast) : [],
                relation: $relation?->getRelationName(),
                related: $relation !== null ? $relation->getRelated()::class : null,
            );
        }

        return $fields;
    }

    /** The belongs-to relation a column is the foreign key of: `<relation>_id`, or the override. */
    private static function relation(Model $model, string $column, ?string $method): ?BelongsTo
    {
        $method ??= str_ends_with($column, '_id') ? Str::camel(Str::beforeLast($column, '_id')) : null;
        if ($method === null || ! method_exists($model, $method) || (new ReflectionMethod($model, $method))->getNumberOfRequiredParameters() > 0) {
            return null;
        }
        try {
            $relation = $model->{$method}();
        } catch (Throwable) {
            return null;
        }

        return $relation instanceof BelongsTo && $relation->getForeignKeyName() === $column ? $relation : null;
    }

    /** @return array<string, string> */
    private static function enumOptions(string $enum): array
    {
        $options = [];
        foreach ($enum::cases() as $case) {
            $value = $case instanceof BackedEnum ? (string) $case->value : $case->name;
            $options[$value] = Str::of($value)->replace('_', ' ')->ucfirst()->toString();
        }

        return $options;
    }
}
