<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogSubmission extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'datetime'];
    }
}
