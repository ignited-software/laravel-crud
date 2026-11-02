<?php

/*
 * The generic data pages (/crud/{resource}): a fallback for records the dedicated screens don't cover.
 * A model is listed only when it opts in here. `read_only` keeps rows whose lifecycle belongs to the
 * model or its flows out of the forms; `screen` links a record to its dedicated page.
 */
return [
    /*
     * slug => options, or just the class of a model that uses Ignited\Crud\Crudable. Options:
     *   model      the Eloquent class (required in the slug form)
     *   label      the page title; default: the slug as a headline
     *   read_only  no create, edit or delete here
     *   list       columns the index shows; default: the first six that fit a table cell
     *   hidden     columns never shown, on top of the model's hidden ones
     *   relations  column => belongs-to method, where the name does not follow `<relation>_id`
     *   screen     [route name, [columns that fill its parameters]]: the record's dedicated page
     *   note       a line shown above the pages
     */
    'resources' => [],

    // The Blade layout the pages extend; it yields `title`, `subtitle` and `content`.
    'layout' => 'crud::layout',

    // The ability every write needs (Gate::define it in the app); null leaves writes to the policies alone.
    'gate' => 'edit-data',

    // Check each model's policy (viewAny, view, create, update, delete, restore). Off: the gate and the
    // route middleware are the only guard.
    'policies' => true,

    // The zone dates are shown and entered in; null = app.timezone.
    'zone' => null,

    'per_page' => 25,
];
