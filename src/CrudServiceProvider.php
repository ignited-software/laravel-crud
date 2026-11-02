<?php

namespace Ignited\Crud;

use Ignited\Crud\Http\CrudController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CrudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/crud.php', 'crud');
        $this->app->singleton(ResourceRegistry::class, fn () => new ResourceRegistry(config('crud.resources', [])));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'crud');
        $this->publishes([__DIR__.'/../config/crud.php' => config_path('crud.php')], 'crud-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/crud')], 'crud-views');

        // The app decides where the pages live and which middleware guards them:
        //   Route::middleware(['web', 'auth'])->group(fn () => Route::crud());
        Route::macro('crud', function (string $prefix = 'crud') {
            Route::controller(CrudController::class)->prefix($prefix.'/{resource}')->name('crud.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}', 'show')->name('show');
                Route::get('/{id}/edit', 'edit')->name('edit');
                Route::put('/{id}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
                Route::post('/{id}/restore', 'restore')->name('restore');
            });
        });
    }
}
