<?php

namespace App\Models;

class CatalogDsp extends CatalogMasterEntry
{
    protected $fillable = ['code', 'name', 'supported_types', 'logo_path', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['supported_types' => 'array']);
    }
}
