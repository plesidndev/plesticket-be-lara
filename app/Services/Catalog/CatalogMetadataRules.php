<?php

namespace App\Services\Catalog;

use Illuminate\Validation\Rule;

class CatalogMetadataRules
{
    public static function rules(bool $complete = false, string $type = 'music'): array
    {
        $required = $complete ? 'required' : 'sometimes';
        $rules = [
            'metadata' => ['sometimes', 'array:release_type,various_artists,artists,contributors,primary_genre,secondary_genre,language,label,copyright_year,copyright_owner,recording_year,recording_owner,upc,release_date,original_release_date,release_time,timezone,territories,stores,preorder,preorder_date,no_preorder_preview,pricing_tier,tracks,explicit,ai_usage,instrumental,lyrics,isrc,version,rights_confirmed'],
            'metadata.various_artists' => ['sometimes', 'boolean'],
            'metadata.release_type' => [$type === 'music' ? $required : 'prohibited', 'in:single,ep,album'],
            'metadata.primary_genre' => [$required, 'string', 'max:100'],
            'metadata.secondary_genre' => ['sometimes', 'nullable', 'string', 'max:100'],
            'metadata.language' => [$required, 'string', 'max:50'],
            'metadata.label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata.copyright_year' => [$required, 'integer', 'between:1900,2200'],
            'metadata.copyright_owner' => [$required, 'string', 'max:255'],
            'metadata.recording_year' => [$required, 'integer', 'between:1900,2200'],
            'metadata.recording_owner' => [$required, 'string', 'max:255'],
            'metadata.upc' => ['sometimes', 'nullable', 'regex:/\A(?:[0-9]{12}|[0-9]{13})\z/'],
            'metadata.release_date' => [$required, 'date_format:Y-m-d'],
            'metadata.original_release_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:metadata.release_date'],
            'metadata.release_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'metadata.timezone' => ['required_with:metadata.release_time', 'nullable', 'string', 'max:40', 'timezone'],
            'metadata.territories' => [$required, 'array', 'min:1', 'max:250'],
            'metadata.territories.*' => ['required', 'string', 'distinct', 'regex:/\A(?:WORLD|[A-Z]{2})\z/'],
            'metadata.stores' => [$required, 'array', 'min:1', 'max:100'],
            'metadata.stores.*' => ['required', 'string', 'distinct', Rule::in(app(CatalogMasterDataService::class)->destinationCodes($type))],
            'metadata.preorder' => ['sometimes', 'boolean'],
            'metadata.preorder_date' => ['required_if:metadata.preorder,true', 'nullable', 'date_format:Y-m-d', 'before:metadata.release_date'],
            'metadata.no_preorder_preview' => ['sometimes', 'boolean'],
            'metadata.pricing_tier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'metadata.version' => ['sometimes', 'nullable', 'string', 'max:100'],
            'metadata.rights_confirmed' => $complete ? ['required', 'accepted'] : ['sometimes', 'boolean'],
            'metadata.tracks' => [$type === 'music' ? $required : 'prohibited', 'array', 'min:1', 'max:100'],
            'metadata.tracks.*' => ['array:id,title,version,artists,contributors,language,explicit,ai_usage,instrumental,lyrics,isrc,preview_start_seconds'],
            'metadata.tracks.*.id' => ['required', 'uuid', 'distinct'],
            'metadata.tracks.*.title' => [$required, 'string', 'max:255'],
            'metadata.tracks.*.version' => ['sometimes', 'nullable', 'string', 'max:100'],
            'metadata.tracks.*.preview_start_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
        $rules += self::people('metadata.artists', $required, ['primary', 'featured']);
        $rules += self::people('metadata.contributors', $type === 'music_video' ? $required : 'sometimes', ['songwriter', 'composer', 'lyricist', 'producer', 'performer', 'director']);
        $rules += self::people('metadata.tracks.*.artists', $required, ['primary', 'featured']);
        $rules += self::people('metadata.tracks.*.contributors', $required, ['songwriter', 'composer', 'lyricist', 'producer', 'performer']);
        foreach (['metadata', 'metadata.tracks.*'] as $prefix) {
            $presence = $prefix === 'metadata' && $type === 'music' ? 'sometimes' : $required;
            $rules[$prefix.'.explicit'] = [$presence, 'in:no,yes,clean'];
            $rules[$prefix.'.ai_usage'] = [$presence, 'in:none,assisted,generated'];
            $rules[$prefix.'.instrumental'] = [$presence, 'boolean'];
            $rules[$prefix.'.lyrics'] = ['sometimes', 'nullable', 'string', 'max:50000'];
            $rules[$prefix.'.isrc'] = ['sometimes', 'nullable', 'regex:/\A[A-Z]{2}[A-Z0-9]{3}[0-9]{7}\z/'];
        }
        $rules['metadata.tracks.*.language'] = [$required, 'string', 'max:50'];

        return $rules;
    }

    private static function people(string $prefix, string $presence, array $roles): array
    {
        return [
            $prefix => [$presence, 'array', 'min:1', 'max:50'],
            $prefix.'.*' => ['array:name,role,spotify_url,apple_music_url'],
            $prefix.'.*.name' => ['required', 'string', 'max:255'],
            $prefix.'.*.role' => ['required', Rule::in($roles)],
            $prefix.'.*.spotify_url' => ['sometimes', 'nullable', 'url:https', 'max:2048'],
            $prefix.'.*.apple_music_url' => ['sometimes', 'nullable', 'url:https', 'max:2048'],
        ];
    }
}
