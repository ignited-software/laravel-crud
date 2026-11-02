<?php

namespace Ignited\Crud\Tests\Fixtures;

use Ignited\Crud\Crudable;
use Illuminate\Database\Eloquent\Model;

class Owner extends Model
{
    use Crudable;

    protected $fillable = ['name'];

    public static function crudOptions(): array
    {
        return ['label' => 'People', 'note' => 'Who owns the things.'];
    }
}
