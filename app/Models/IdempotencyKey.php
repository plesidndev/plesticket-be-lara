<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = ['scope', 'user_id', 'key', 'resource_id'];

    protected function casts(): array
    {
        return ['resource_id' => 'integer', 'user_id' => 'integer'];
    }
}
