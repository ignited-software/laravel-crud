<?php

namespace Ignited\Crud\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Thing extends Model
{
    use SoftDeletes;

    protected $fillable = ['owner_id', 'name', 'notes', 'status', 'is_active', 'due_on', 'meta', 'api_key'];

    protected function casts(): array
    {
        return ['status' => Status::class, 'is_active' => 'boolean', 'due_on' => 'date', 'meta' => 'array', 'api_key' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $thing) {
            if ($thing->name === 'forbidden') {
                throw new LogicException('That name is not allowed');
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }
}
