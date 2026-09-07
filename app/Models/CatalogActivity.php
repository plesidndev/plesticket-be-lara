<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogActivity extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'context' => 'array', 'internal' => 'boolean', 'created_at' => 'datetime'];
    }
}
