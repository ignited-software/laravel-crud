<?php

namespace Ignited\Crud;

/** How a column is shown and edited on the generic data pages. */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Integer = 'integer';
    case Number = 'number';
    case Switch = 'switch';
    case Date = 'date';
    case DateTime = 'datetime';
    case Json = 'json';
    case Select = 'select';
    case Relation = 'relation';
    case Secret = 'secret';

    /** Offered as an index filter: a short list of values to pick from. */
    public function filterable(): bool
    {
        return in_array($this, [self::Select, self::Switch, self::Relation], true);
    }

    public function searchable(): bool
    {
        return $this === self::Text || $this === self::Textarea;
    }
}
