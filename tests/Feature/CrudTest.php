<?php

use Ignited\Crud\ResourceRegistry;
use Ignited\Crud\Tests\Fixtures\Owner;
use Ignited\Crud\Tests\Fixtures\Thing;
use Ignited\Crud\Tests\Fixtures\User;
use Illuminate\Support\Facades\Gate;

it('registers a Crudable model by its class, with the model\'s own options', function () {
    $owners = app(ResourceRegistry::class)->find('owners');

    expect($owners->model)->toBe(Owner::class)->and($owners->label)->toBe('People')->and($owners->note)->toBe('Who owns the things.');
});

it('lets the config override a Crudable model\'s options', function () {
    config(['crud.resources' => ['owners' => ['model' => Owner::class, 'label' => 'Owners']]]);
    app()->forgetInstance(ResourceRegistry::class);

    expect(app(ResourceRegistry::class)->find('owners'))->label->toBe('Owners')->note->toBe('Who owns the things.');
});

it('renders every page of every resource, and never a hidden or encrypted value', function (string $slug) {
    $editor = $this->editor();
    $editor->forceFill(['remember_token' => 'remember-me-token-xyz'])->save();
    $owner = Owner::query()->create(['name' => 'Ann']);
    $thing = Thing::query()->create(['owner_id' => $owner->id, 'name' => 'Lamp', 'api_key' => 'crud-secret-key-xyz', 'meta' => ['a' => 1]]);
    $record = ['owners' => $owner, 'things' => $thing, 'users' => $editor][$slug];
    $secrets = ['crud-secret-key-xyz', 'remember-me-token-xyz', $editor->getAuthPassword()];

    $pages = [
        $this->actingAs($editor)->get("/crud/{$slug}")->assertOk(),
        $this->actingAs($editor)->get("/crud/{$slug}/{$record->getKey()}")->assertOk(),
        $this->actingAs($editor)->get("/crud/{$slug}?trashed=with&q=a&sort=created_at&dir=asc")->assertOk(),
    ];
    if ($slug === 'users') {
        $this->actingAs($editor)->get("/crud/{$slug}/create")->assertForbidden();
        $this->actingAs($editor)->delete("/crud/{$slug}/{$record->getKey()}")->assertForbidden();
    } else {
        $pages[] = $this->actingAs($editor)->get("/crud/{$slug}/create")->assertOk();
        $pages[] = $this->actingAs($editor)->get("/crud/{$slug}/{$record->getKey()}/edit")->assertOk();
    }

    foreach ($pages as $page) {
        foreach ($secrets as $secret) {
            $page->assertDontSee($secret, false);
        }
    }
})->with(['owners', 'things', 'users']);

it('keeps every write from a user without the gate ability', function () {
    $thing = Thing::query()->create(['name' => 'Lamp']);
    $reader = $this->reader();

    $this->actingAs($reader)->get('/crud/things')->assertOk()->assertDontSee('>New<', false);
    $this->actingAs($reader)->get('/crud/things/create')->assertForbidden();
    $this->actingAs($reader)->post('/crud/things', [])->assertForbidden();
    $this->actingAs($reader)->put("/crud/things/{$thing->id}", [])->assertForbidden();
    $this->actingAs($reader)->delete("/crud/things/{$thing->id}")->assertForbidden();
    expect(Thing::query()->whereKey($thing->id)->exists())->toBeTrue();
});

it('leaves writes to the policies when no gate is configured', function () {
    config(['crud.gate' => null]);

    $this->actingAs($this->reader())->post('/crud/owners', ['name' => 'Bo'])->assertSessionHasNoErrors();
    expect(Owner::query()->where('name', 'Bo')->exists())->toBeTrue();
});

it('checks the model policy when policies are on', function () {
    config(['crud.policies' => true]);
    $editor = $this->editor();

    $this->actingAs($editor)->get('/crud/owners')->assertForbidden();
    Gate::before(fn (User $user) => $user->is_editor ?: null);
    $this->actingAs($editor)->get('/crud/owners')->assertOk();
});

it('answers 404 for a resource that is not registered', function () {
    $this->actingAs($this->editor())->get('/crud/password-resets')->assertNotFound();
});

it('creates, updates, soft-deletes and restores a record', function () {
    $editor = $this->editor();
    $owner = Owner::query()->create(['name' => 'Ann']);

    $this->actingAs($editor)->post('/crud/things', ['owner_id' => $owner->id, 'name' => 'Lamp', 'status' => 'done', 'is_active' => '0', 'due_on' => '2026-11-03', 'meta' => '{"watts": 40}'])
        ->assertSessionHasNoErrors();
    $thing = Thing::query()->where('name', 'Lamp')->sole();
    expect($thing)->owner_id->toBe($owner->id)->is_active->toBeFalse()->meta->toBe(['watts' => 40])
        ->and($thing->due_on->format('Y-m-d'))->toBe('2026-11-03');

    $this->actingAs($editor)->put("/crud/things/{$thing->id}", ['name' => 'Desk lamp', 'status' => 'open', 'is_active' => '1', 'due_on' => ''])->assertSessionHasNoErrors();
    expect($thing->fresh())->name->toBe('Desk lamp')->due_on->toBeNull();

    $this->actingAs($editor)->delete("/crud/things/{$thing->id}")->assertRedirect('/crud/things');
    expect($thing->fresh()->trashed())->toBeTrue();
    $this->actingAs($editor)->get('/crud/things')->assertDontSee('Desk lamp');
    $this->actingAs($editor)->get('/crud/things?trashed=only')->assertSee('Desk lamp')->assertSee('trashed');

    $this->actingAs($editor)->post("/crud/things/{$thing->id}/restore")->assertSessionHasNoErrors();
    expect($thing->fresh()->trashed())->toBeFalse();
});

// A varchar's length becomes a max rule on MariaDB/MySQL; SQLite (this suite) does not report it.
it('validates from the columns: required, types, enums, json and foreign keys', function () {
    $this->actingAs($this->editor())->post('/crud/things', ['name' => '', 'status' => 'lost', 'is_active' => 'maybe', 'meta' => '{nope', 'owner_id' => 999])
        ->assertSessionHasErrors(['name', 'status', 'is_active', 'meta', 'owner_id']);
});

it('writes only fillable columns', function () {
    $thing = Thing::query()->create(['name' => 'Lamp']);

    $this->actingAs($this->editor())->put("/crud/things/{$thing->id}", ['name' => 'Kept', 'status' => 'open', 'is_active' => '1', 'id' => 999, 'deleted_at' => '2026-01-01T00:00', 'created_at' => '2000-01-01T00:00'])
        ->assertSessionHasNoErrors();

    expect($thing->fresh())->name->toBe('Kept')->trashed()->toBeFalse()->created_at->year->toBe($thing->created_at->year)
        ->and(Thing::query()->whereKey(999)->exists())->toBeFalse();
});

it('treats an encrypted column as write-only: blank keeps it, filled replaces it', function () {
    $thing = Thing::query()->create(['name' => 'Lamp', 'api_key' => 'kept-key']);
    $editor = $this->editor();
    $values = ['name' => 'Lamp', 'status' => 'open', 'is_active' => '1'];

    $this->actingAs($editor)->get("/crud/things/{$thing->id}/edit")->assertSee('name="api_key" type="password"', false)->assertDontSee('kept-key');
    $this->actingAs($editor)->put("/crud/things/{$thing->id}", [...$values, 'api_key' => ''])->assertSessionHasNoErrors();
    expect($thing->fresh()->api_key)->toBe('kept-key');
    $this->actingAs($editor)->put("/crud/things/{$thing->id}", [...$values, 'api_key' => 'new-key']);
    expect($thing->fresh()->api_key)->toBe('new-key');
});

it('turns a database constraint and a model guard into form errors, not a 500', function () {
    Thing::query()->create(['name' => 'Lamp']);
    $editor = $this->editor();

    $this->actingAs($editor)->post('/crud/things', ['name' => 'Lamp', 'status' => 'open', 'is_active' => '1'])->assertRedirect()->assertSessionHasErrors('crud');
    $this->actingAs($editor)->post('/crud/things', ['name' => 'forbidden', 'status' => 'open', 'is_active' => '1'])
        ->assertRedirect()->assertSessionHasErrors(['crud' => 'That name is not allowed']);
});

it('sorts, searches and filters the index', function () {
    $ann = Owner::query()->create(['name' => 'Ann']);
    Thing::query()->create(['owner_id' => $ann->id, 'name' => 'Aula']);
    Thing::query()->create(['owner_id' => $ann->id, 'name' => 'Zwembad']);
    Thing::query()->create(['name' => 'Elders', 'status' => 'done']);
    $editor = $this->editor();

    $names = fn (string $query) => $this->actingAs($editor)->get("/crud/things?{$query}")->assertOk()->viewData('rows')->pluck('name')->all();

    expect($names('sort=name&dir=asc'))->toBe(['Aula', 'Elders', 'Zwembad'])
        ->and($names('sort=name&dir=desc'))->toBe(['Zwembad', 'Elders', 'Aula'])
        ->and($names('q=wem'))->toBe(['Zwembad'])
        ->and($names("filter[owner_id]={$ann->id}&sort=name&dir=asc"))->toBe(['Aula', 'Zwembad'])
        ->and($names('filter[status]=done'))->toBe(['Elders'])
        ->and($names('sort=api_key'))->toHaveCount(3);
});

it('links a related record to its own data page', function () {
    $ann = Owner::query()->create(['name' => 'Ann']);
    $thing = Thing::query()->create(['owner_id' => $ann->id, 'name' => 'Lamp']);

    $this->actingAs($this->editor())->get("/crud/things/{$thing->id}")->assertSee("/crud/owners/{$ann->id}", false)->assertSee('Ann');
});
