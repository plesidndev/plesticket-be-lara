<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class CatalogUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fileRules = match ($this->input('kind')) {
            'artwork' => ['image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:'.config('catalog.max_artwork_kb'), 'dimensions:ratio=1,min_width='.config('catalog.artwork_min_pixels').',min_height='.config('catalog.artwork_min_pixels')],
            'audio' => ['mimetypes:audio/wav,audio/x-wav,audio/vnd.wave,audio/wave,audio/flac,audio/x-flac', 'extensions:wav,flac', 'max:'.config('catalog.max_audio_kb')],
            'video' => ['mimetypes:video/mp4,video/quicktime', 'extensions:mp4,mov', 'max:'.config('catalog.max_video_kb')],
            default => [],
        };

        return [
            'kind' => ['required', 'in:artwork,audio,video'],
            'track_id' => ['required_if:kind,audio', 'prohibited_unless:kind,audio', 'uuid'],
            'file' => ['required', 'file', ...$fileRules],
        ];
    }
}
