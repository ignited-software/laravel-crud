<?php

namespace Ignited\Crud;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Stringable;

/** One column of a resource: how it is shown, edited, validated and written back. Knows no model in particular. */
final readonly class Field
{
    /** Columns a related record is named by, in order of preference. */
    public const LABEL_COLUMNS = ['name', 'title', 'email', 'key', 'block_key'];

    /**
     * @param  array<string, string>  $options  value => label, for a select
     * @param  class-string<Model>|null  $related
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public bool $editable,
        public bool $nullable,
        public bool $hasDefault,
        public ?int $maxLength = null,
        public array $options = [],
        public ?string $relation = null,
        public ?string $related = null,
    ) {}

    public function label(): string
    {
        return Str::of($this->relation ?? $this->name)->snake()->replace('_', ' ')->ucfirst()->toString();
    }

    public function isSecret(): bool
    {
        return $this->type === FieldType::Secret;
    }

    /** The value as text, for lists and the detail page; a secret never reaches this. */
    public function display(Model $model, int $limit = 0): string
    {
        if ($this->type === FieldType::Relation) {
            $related = $model->relationLoaded($this->relation) ? $model->getRelation($this->relation) : $model->{$this->relation};

            return $related instanceof Model ? self::nameOf($related) : (string) $model->getAttribute($this->name);
        }

        $value = $model->getAttribute($this->name);
        $text = match (true) {
            $value === null => '',
            $value instanceof BackedEnum => (string) $value->value,
            is_bool($value) => $value ? 'Yes' : 'No',
            $value instanceof DateTimeInterface => $this->type === FieldType::Date
                ? $value->format('Y-m-d')
                : CarbonImmutable::instance($value)->setTimezone(self::zone())->format('Y-m-d H:i'),
            is_array($value) || $value instanceof \ArrayAccess || $value instanceof \JsonSerializable => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ($limit ? 0 : JSON_PRETTY_PRINT)),
            is_scalar($value) || $value instanceof Stringable => (string) $value,
            default => get_debug_type($value),
        };

        return $limit > 0 ? Str::limit($text, $limit) : $text;
    }

    /** What the form field starts with: the stored value in the shape its input takes. A secret starts blank. */
    public function formValue(Model $model): string
    {
        if ($this->isSecret() || ! $model->exists) {
            return '';
        }
        $value = $model->getAttribute($this->name);

        return match (true) {
            $value === null => '',
            $this->type === FieldType::Json => (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $value instanceof DateTimeInterface => $this->type === FieldType::Date
                ? $value->format('Y-m-d')
                : CarbonImmutable::instance($value)->setTimezone(self::zone())->format('Y-m-d\TH:i'),
            $value instanceof BackedEnum => (string) $value->value,
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /** @return list<mixed> */
    public function rules(bool $creating): array
    {
        $optional = $this->nullable || $this->hasDefault || $this->type === FieldType::Switch || ($this->isSecret() && ! $creating);
        $rules = [$optional ? 'nullable' : 'required'];

        return [...$rules, ...match ($this->type) {
            FieldType::Text, FieldType::Secret => array_filter(['string', $this->maxLength ? "max:{$this->maxLength}" : null]),
            FieldType::Textarea => ['string'],
            FieldType::Integer => ['integer'],
            FieldType::Number => ['numeric'],
            FieldType::Switch => ['boolean'],
            FieldType::Date => ['date_format:Y-m-d'],
            FieldType::DateTime => ['date'],
            FieldType::Json => ['json'],
            FieldType::Select => [Rule::in(array_keys($this->options))],
            FieldType::Relation => [Rule::exists((new $this->related)->getTable(), (new $this->related)->getKeyName())],
        }];
    }

    /**
     * The validated input as the model takes it. `$present` is false when the key was left out (a
     * blank secret): the model keeps its value.
     *
     * @return array{0: bool, 1: mixed}
     */
    public function fromInput(mixed $value): array
    {
        if ($this->type === FieldType::Switch) {
            return [true, filter_var($value, FILTER_VALIDATE_BOOLEAN)];
        }
        if ($value === null || $value === '') {
            return [! $this->isSecret(), null];
        }

        return [true, match ($this->type) {
            FieldType::Json => json_decode((string) $value, true),
            FieldType::Integer => (int) $value,
            FieldType::DateTime => CarbonImmutable::parse((string) $value, self::zone())->setTimezone(config('app.timezone')),
            default => $value,
        }];
    }

    /** The zone dates are shown and entered in: `crud.zone`, else the app's. */
    public static function zone(): string
    {
        return config('crud.zone') ?? config('app.timezone');
    }

    public static function nameOf(Model $model): string
    {
        foreach (self::LABEL_COLUMNS as $column) {
            $value = $model->getAttribute($column);
            if (is_scalar($value) && $value !== '') {
                return (string) $value;
            }
        }
        $number = $model->getAttribute('number');

        return $number !== null ? "#{$number}" : Str::limit((string) $model->getKey(), 13, '…');
    }
}
