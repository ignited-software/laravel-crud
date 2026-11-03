# ignited/laravel-crud

Generic data pages for Eloquent models: index (search, filters, sort, trash), show, create, edit,
delete and restore, built from each model's columns and casts. A fallback next to an app's dedicated
screens, so a new table shows up without new views.

Used by Atlas and the FourIQ AI Centre.

## What it reads from a model

- **Columns** (`Schema::getColumns`) and **casts** pick the input: text, textarea, integer, number,
  switch, date, datetime, JSON, enum select, belongs-to select.
- **Only `$fillable` columns are written**; `id`, timestamps and generated columns never are.
- **Hidden, `encrypted` and `hashed` columns are write-only**: never shown, a password field in the
  form, and a blank value keeps what is stored.
- A `<relation>_id` column with a matching `belongsTo` becomes a relation select and links to the
  related record's page when that model is registered too.
- Model guards (`saving` hooks that throw) and database constraints come back as form errors, not a 500.

## Install

```bash
composer require ignited/laravel-crud
php artisan vendor:publish --tag=crud-config      # config/crud.php
php artisan vendor:publish --tag=crud-views       # optional: resources/views/vendor/crud
```

Register the routes where your app wants them, with its own middleware:

```php
// routes/web.php
Route::middleware(['web', 'auth'])->group(fn () => Route::crud());   // /crud/{resource}/…
```

Define the ability writes need (`crud.gate`, default `edit-data`):

```php
Gate::define('edit-data', fn (User $user) => $user->isEditor());
```

## Registering models

In `config/crud.php`, per model either options or just the class:

```php
'resources' => [
    'portals' => ['model' => Portal::class, 'list' => ['key', 'name'], 'screen' => ['portals.show', ['id']]],
    'versions' => ['model' => InstructionVersion::class, 'read_only' => true, 'note' => 'Append-only.'],
    Project::class,   // uses Ignited\Crud\Crudable
],
```

A model that uses the `Crudable` trait can carry its own options; the config still wins:

```php
class Project extends Model
{
    use Crudable;

    public static function crudOptions(): array
    {
        return ['list' => ['slug', 'name', 'status'], 'screen' => ['projects.show', ['slug']]];
    }
}
```

Options: `model`, `label`, `read_only`, `list`, `hidden`, `relations` (column => belongs-to method),
`screen` ([route name, [columns]]), `note`. See `config/crud.php`.

## Config

| Key | Default | |
|---|---|---|
| `layout` | `crud::layout` | the Blade layout; it yields `title`, `subtitle` and `content` |
| `gate` | `edit-data` | the ability every write needs; `null` leaves writes to the policies |
| `policies` | `true` | check each model's policy (`viewAny`, `view`, `create`, `update`, `delete`, `restore`) |
| `zone` | `null` | the zone dates are shown and entered in; `null` = `app.timezone` |
| `per_page` | `25` | |

The views use Bootstrap 5 classes; publish them to restyle. Deleting asks for confirmation through a
`data-confirm` attribute on the form, which the app's own JavaScript handles.

## Tests

```bash
composer install && composer test && composer lint
```

The suite runs on SQLite, which does not report a varchar's length, so the `max:` rule taken from it
is only exercised on MariaDB/MySQL.

## License

MIT, see [LICENSE](LICENSE).
