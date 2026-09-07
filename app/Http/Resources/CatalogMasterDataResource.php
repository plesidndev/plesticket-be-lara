<?php

namespace App\Http\Resources;

use App\Models\CatalogDsp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CatalogMasterDataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'is_active' => $this->is_active, 'sort_order' => $this->sort_order, 'logo_url' => $this->when($this->resource instanceof CatalogDsp, fn () => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null), 'supported_types' => $this->when($this->resource instanceof CatalogDsp, fn () => $this->supported_types)];
    }
}
