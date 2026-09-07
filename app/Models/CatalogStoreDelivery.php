<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogStoreDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['live_at' => 'datetime'];
    }
}
