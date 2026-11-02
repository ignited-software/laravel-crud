<?php

namespace Ignited\Crud;

use Illuminate\Support\Str;

/**
 * Lets a model carry its own data-page options, so `config/crud.php` can list just the class.
 * Options set in the config still win over these.
 */
trait Crudable
{
    /** @return array<string, mixed> the same keys as a `config/crud.php` entry (label, list, hidden, read_only, …) */
    public static function crudOptions(): array
    {
        return [];
    }

    /** The URL segment: `ServiceLink` → `service-links`. */
    public static function crudSlug(): string
    {
        return Str::kebab(Str::pluralStudly(class_basename(static::class)));
    }
}
