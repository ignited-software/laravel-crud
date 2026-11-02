<?php

namespace Ignited\Crud\Tests;

use Ignited\Crud\CrudServiceProvider;
use Ignited\Crud\Tests\Fixtures\Owner;
use Ignited\Crud\Tests\Fixtures\Thing;
use Ignited\Crud\Tests\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CrudServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('crud.resources', [
            Owner::class,
            'things' => ['model' => Thing::class, 'list' => ['name', 'owner_id', 'status', 'is_active']],
            'users' => ['model' => User::class, 'read_only' => true],
        ]);
        $app['config']->set('crud.policies', false);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware(['web', 'auth'])->group(fn () => Route::crud());
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->boolean('is_editor')->default(false);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('owners', function (Blueprint $t) {
            $t->id();
            $t->string('name', 40);
            $t->timestamps();
        });
        Schema::create('things', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->nullable()->constrained();
            $t->string('name')->unique();
            $t->text('notes')->nullable();
            $t->string('status')->default('open');
            $t->boolean('is_active')->default(true);
            $t->date('due_on')->nullable();
            $t->json('meta')->nullable();
            $t->text('api_key')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('edit-data', fn (User $user) => $user->is_editor);
    }

    protected function editor(): User
    {
        return User::query()->create(['name' => 'Ed', 'email' => 'ed@test.dev', 'password' => 'secret-password-1', 'is_editor' => true]);
    }

    protected function reader(): User
    {
        return User::query()->create(['name' => 'Rea', 'email' => 'rea@test.dev', 'password' => 'secret-password-2']);
    }
}
