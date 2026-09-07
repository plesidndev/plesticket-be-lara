<?php

namespace Tests\Feature;

use App\Models\CatalogDsp;
use App\Models\User;
use Database\Seeders\CatalogDspSeeder;
use Database\Seeders\CatalogMasterDataSeeder;
use Database\Seeders\CatalogTerritorySeeder;
use Database\Seeders\CatalogTimezoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $artist;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogMasterDataSeeder::class);
        Storage::fake('catalog');
        config()->set('catalog.disk', 'catalog');
        config()->set('catalog.artwork_min_pixels', 10);
        $this->artist = User::factory()->create(['is_plesconnect_user' => true, 'is_organizer' => false, 'is_active' => true, 'role' => 'REGISTERED_USER']);
        $this->admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'is_active' => true]);
    }

    public function test_catalog_requires_active_plesconnect_identity_but_not_organizer_status(): void
    {
        $this->getJson('/api/catalog/releases')->assertUnauthorized();
        $buyer = User::factory()->create(['is_plesconnect_user' => false]);
        $this->actingAs($buyer, 'api')->getJson('/api/catalog/releases')->assertForbidden();
        $this->actingAs($this->artist, 'api')->getJson('/api/catalog/options')->assertOk();
        $this->assertFalse($this->artist->is_organizer);
        $this->artist->update(['is_active' => false]);
        $this->getJson('/api/catalog/releases')->assertForbidden();
    }

    public function test_minimal_drafts_allow_incomplete_metadata_but_submission_does_not(): void
    {
        $id = $this->draft()['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertUnprocessable()->assertJsonValidationErrors(['metadata.artists', 'metadata.stores']);
        $this->assertDatabaseCount('catalog_submissions', 0);
        $this->getJson("/api/catalog/releases/$id")->assertJsonPath('data.status', 'draft');
    }

    public function test_drafts_reject_unknown_metadata_and_protected_attributes(): void
    {
        $this->actingAs($this->artist, 'api')->postJson('/api/catalog/releases', [
            'type' => 'music', 'title' => 'Release', 'status' => 'live',
        ])->assertUnprocessable();
        $this->postJson('/api/catalog/releases', [
            'type' => 'music', 'title' => 'Release', 'metadata' => ['provider_secret' => 'hidden'],
        ])->assertUnprocessable();
        $id = $this->draft()['id'];
        $this->patchJson("/api/catalog/releases/$id", ['type' => 'music_video'])->assertUnprocessable();
    }

    public function test_ownership_is_enforced_for_list_read_update_submit_history_and_assets(): void
    {
        $release = $this->completeDraft();
        $id = $release['id'];
        $asset = $release['assets'][0]['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $other = User::factory()->create(['is_plesconnect_user' => true, 'is_active' => true]);
        $this->actingAs($other, 'api')->getJson('/api/catalog/releases')->assertJsonPath('meta.total', 0);
        $this->getJson("/api/catalog/releases/$id")->assertNotFound();
        $this->patchJson("/api/catalog/releases/$id", ['title' => 'Stolen'])->assertNotFound();
        $this->postJson("/api/catalog/releases/$id/submit")->assertNotFound();
        $this->getJson("/api/catalog/releases/$id/submissions/1")->assertNotFound();
        $this->getJson("/api/catalog/releases/$id/assets/$asset")->assertNotFound();
        $this->deleteJson("/api/catalog/releases/$id/assets/$asset")->assertNotFound();
        $this->postJson("/api/catalog/releases/$id/assets", ['kind' => 'artwork', 'file' => UploadedFile::fake()->image('cover.png', 10, 10)])->assertNotFound();
        $this->deleteJson("/api/catalog/releases/$id")->assertNotFound();
    }

    public function test_music_submission_requires_files_and_locks_all_draft_mutations(): void
    {
        $metadata = $this->metadata();
        $id = $this->draft($metadata)['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertUnprocessable()->assertJsonValidationErrors(['assets.artwork', 'metadata.tracks.0.audio']);
        $this->uploadMusic($id, $metadata['tracks'][0]['id']);
        $response = $this->postJson("/api/catalog/releases/$id/submit")->assertOk()
            ->assertJsonPath('data.version', 1)->assertJsonPath('data.status', 'waiting_for_review')->assertJsonPath('data.editable', false);
        $asset = $response->json('data.assets.0.id');
        $this->postJson("/api/catalog/releases/$id/submit")->assertConflict();
        $this->patchJson("/api/catalog/releases/$id", ['title' => 'Changed'])->assertConflict();
        $this->deleteJson("/api/catalog/releases/$id/assets/$asset")->assertConflict();
        $this->postJson("/api/catalog/releases/$id/assets", ['kind' => 'artwork', 'file' => UploadedFile::fake()->image('new.png', 10, 10)])->assertConflict();
        $this->deleteJson("/api/catalog/releases/$id")->assertConflict();
        $this->assertDatabaseCount('catalog_submissions', 1);
    }

    public function test_corrections_preserve_original_submission_and_assets(): void
    {
        $release = $this->completeDraft();
        $id = $release['id'];
        $oldArtwork = collect($release['assets'])->firstWhere('kind', 'artwork')['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->actingAs($this->admin, 'api')->postJson("/api/admin/catalog/releases/$id/status", [
            'status' => 'changes_requested', 'version' => 1, 'message' => 'Please correct the title.',
            'fields' => [['field' => 'title', 'message' => 'Match the artwork.']],
        ])->assertOk();
        $this->actingAs($this->artist, 'api')->patchJson("/api/catalog/releases/$id", ['title' => 'Corrected'])->assertOk();
        $this->postJson("/api/catalog/releases/$id/assets", ['kind' => 'artwork', 'file' => UploadedFile::fake()->image('replacement.png', 12, 12)])->assertCreated();
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson("/api/catalog/releases/$id/submissions/1")->assertOk()->assertJsonPath('data.snapshot.title', 'My release')
            ->assertJsonFragment(['id' => $oldArtwork]);
        $this->getJson("/api/catalog/releases/$id/submissions/2")->assertJsonPath('data.snapshot.title', 'Corrected');
        $this->get("/api/catalog/releases/$id/assets/$oldArtwork")->assertOk();
        $this->actingAs($this->admin, 'api')->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'under_review', 'version' => 1])->assertConflict();
    }

    public function test_only_admins_can_review_and_internal_fields_never_leak_to_customers(): void
    {
        $id = $this->completeDraft()['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->getJson('/api/admin/catalog/releases')->assertForbidden();
        $this->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'approved', 'version' => 1])->assertForbidden();
        $this->approve($id);
        $this->putJson("/api/admin/catalog/releases/$id/distribution", $this->distributionData())->assertOk();
        $this->postJson("/api/admin/catalog/releases/$id/notes", ['message' => 'PRIVATE-ONERPM-NOTE'])->assertOk();
        $this->patchJson("/api/admin/catalog/releases/$id/assignment", ['assigned_admin_id' => $this->admin->id])->assertOk();
        $this->getJson("/api/admin/catalog/releases/$id")->assertJsonPath('data.distribution.provider', 'onerpm');
        foreach (["/api/catalog/releases/$id", '/api/catalog/releases', "/api/catalog/releases/$id/submissions/1"] as $url) {
            $response = $this->actingAs($this->artist, 'api')->getJson($url)->assertOk();
            $this->assertStringNotContainsString('onerpm', $response->getContent());
            $this->assertStringNotContainsString('PRIVATE-ONERPM-NOTE', $response->getContent());
            $this->assertStringNotContainsString('assigned_admin_id', $response->getContent());
            $this->assertStringNotContainsString('"disk"', $response->getContent());
            $this->assertStringNotContainsString('"path"', $response->getContent());
        }
        $this->getJson('/api/catalog/releases?assigned_admin_id='.$this->admin->id)->assertUnprocessable();
    }

    public function test_distribution_flow_tracks_partial_availability_and_rejects_invalid_transitions(): void
    {
        $metadata = $this->metadata();
        $metadata['stores'] = ['spotify', 'apple_music'];
        $id = $this->completeDraft($metadata)['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->actingAs($this->admin, 'api')->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'live', 'version' => 1])->assertConflict();
        $this->approve($id);
        $this->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'processing_distribution', 'version' => 1])->assertUnprocessable();
        $this->putJson("/api/admin/catalog/releases/$id/distribution", $this->distributionData())->assertOk();
        $this->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'processing_distribution', 'version' => 1])->assertOk();
        $this->putJson("/api/admin/catalog/releases/$id/stores/spotify", ['version' => 1, 'status' => 'live', 'url' => 'https://open.spotify.com/album/example'])->assertOk();
        $this->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'live', 'version' => 1])->assertUnprocessable();
        $this->putJson("/api/admin/catalog/releases/$id/stores/apple_music", ['version' => 1, 'status' => 'live', 'url' => 'https://music.apple.com/album/example'])->assertOk();
        $this->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'scheduled', 'version' => 1])->assertOk();
        $this->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'live', 'version' => 1])->assertOk();
        $this->putJson("/api/admin/catalog/releases/$id/stores/spotify", ['version' => 1, 'status' => 'removed'])->assertOk()->assertJsonPath('data.status', 'processing_distribution');
    }

    public function test_music_video_has_its_own_metadata_assets_and_destinations(): void
    {
        $metadata = $this->metadata();
        unset($metadata['tracks'], $metadata['release_type']);
        $metadata += ['explicit' => 'no', 'instrumental' => false, 'ai_usage' => 'none', 'contributors' => [['name' => 'Writer', 'role' => 'songwriter']]];
        $metadata['stores'] = ['apple_music_video'];
        $id = $this->draft($metadata, 'music_video')['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertUnprocessable()->assertJsonValidationErrors(['assets.video']);
        $this->postJson("/api/catalog/releases/$id/assets", [
            'kind' => 'video', 'file' => UploadedFile::fake()->create('music.mp4', 100, 'video/mp4'),
        ])->assertCreated();
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $metadata['stores'] = ['spotify'];
        $this->actingAs($this->artist, 'api')->postJson('/api/catalog/releases', ['type' => 'music_video', 'title' => 'Wrong store', 'metadata' => $metadata])->assertUnprocessable();
    }

    public function test_uploads_reject_wrong_content_size_dimensions_and_track_ownership(): void
    {
        $metadata = $this->metadata();
        $id = $this->draft($metadata)['id'];
        $disguised = UploadedFile::fake()->createWithContent('fake.wav', '<?php echo "bad";');
        $this->postJson("/api/catalog/releases/$id/assets", [
            'kind' => 'audio', 'track_id' => $metadata['tracks'][0]['id'],
            'file' => new UploadedFile($disguised->getPathname(), 'fake.wav', 'audio/wav', null, true),
        ])->assertUnprocessable();
        $this->postJson("/api/catalog/releases/$id/assets", ['kind' => 'artwork', 'file' => UploadedFile::fake()->image('small.png', 5, 5)])->assertUnprocessable();
        $this->postJson("/api/catalog/releases/$id/assets", ['kind' => 'audio', 'track_id' => (string) Str::uuid(), 'file' => $this->audio()])->assertUnprocessable();
        config()->set('catalog.max_audio_kb', 1);
        $this->postJson("/api/catalog/releases/$id/assets", ['kind' => 'audio', 'track_id' => $metadata['tracks'][0]['id'], 'file' => UploadedFile::fake()->create('large.wav', 2, 'audio/wav')])->assertUnprocessable();
        $this->assertCount(0, Storage::disk('catalog')->allFiles());
    }

    public function test_admin_exports_the_requested_snapshot_with_original_files(): void
    {
        $release = $this->completeDraft();
        $id = $release['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->getJson("/api/admin/catalog/releases/$id/submissions/1/export")->assertForbidden();
        $response = $this->actingAs($this->admin, 'api')->get("/api/admin/catalog/releases/$id/submissions/1/export")->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $snapshot = json_decode($zip->getFromName('metadata.json'), true);
            $this->assertSame(1, $snapshot['version']);
            $this->assertSame('My release', $snapshot['title']);
            $this->assertNotFalse($zip->getFromName('metadata.csv'));
            foreach ($snapshot['assets'] as $asset) {
                $contents = $zip->getFromName('assets/'.$asset['id'].'-'.$asset['original_name']);
                $this->assertSame($asset['sha256'], hash('sha256', $contents));
            }
            $zip->close();
        } finally {
            @unlink($path);
        }
        $this->assertDatabaseHas('catalog_activities', ['release_id' => $id, 'action' => 'exported', 'internal' => true]);
    }

    public function test_change_requests_do_not_unlock_submitted_releases_without_admin_review(): void
    {
        $id = $this->completeDraft()['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->postJson("/api/catalog/releases/$id/change-requests", ['message' => 'Please let me fix the spelling.'])->assertOk()->assertJsonPath('data.editable', false)->assertJsonPath('data.has_change_request', true);
        $this->patchJson("/api/catalog/releases/$id", ['title' => 'Changed'])->assertConflict();
        $this->assertDatabaseHas('catalog_activities', ['release_id' => $id, 'action' => 'change_requested']);
        $this->postJson("/api/catalog/releases/$id/change-requests", ['message' => 'Duplicate'])->assertConflict();
        $this->actingAs($this->admin, 'api')->getJson('/api/admin/catalog/releases?has_change_request=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->postJson("/api/admin/catalog/releases/$id/status", ['version' => 1, 'status' => 'under_review'])->assertConflict();
        $this->postJson("/api/admin/catalog/releases/$id/status", ['version' => 1, 'status' => 'changes_requested', 'message' => 'Please make your correction.'])->assertOk()->assertJsonPath('data.has_change_request', false);
    }

    public function test_review_reasons_assignment_and_version_are_validated(): void
    {
        $id = $this->completeDraft()['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->actingAs($this->admin, 'api')->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'rejected', 'version' => 1])->assertUnprocessable();
        $this->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'changes_requested', 'version' => 1])->assertUnprocessable();
        $this->patchJson("/api/admin/catalog/releases/$id/assignment", ['assigned_admin_id' => $this->artist->id])->assertUnprocessable();
        $this->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'under_review'])->assertUnprocessable();
        $this->admin->update(['is_active' => false]);
        $this->getJson('/api/admin/catalog/releases')->assertForbidden();
    }

    public function test_a_pending_change_request_blocks_a_new_distribution_record(): void
    {
        $id = $this->completeDraft()['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->approve($id);
        $this->actingAs($this->artist, 'api')->postJson("/api/catalog/releases/$id/change-requests", ['message' => 'The master needs replacing.'])->assertOk();
        $this->actingAs($this->admin, 'api')->putJson("/api/admin/catalog/releases/$id/distribution", $this->distributionData())->assertConflict();
        $this->getJson("/api/admin/catalog/releases/$id")->assertJsonPath('data.distribution', null);
    }

    public function test_draft_deletion_cleans_up_private_assets(): void
    {
        $id = $this->completeDraft()['id'];
        $this->assertCount(2, Storage::disk('catalog')->allFiles());
        $this->deleteJson("/api/catalog/releases/$id")->assertOk();
        $this->assertDatabaseMissing('catalog_releases', ['id' => $id]);
        $this->assertCount(0, Storage::disk('catalog')->allFiles());
    }

    private function draft(array $metadata = [], string $type = 'music'): array
    {
        return $this->actingAs($this->artist, 'api')->postJson('/api/catalog/releases', [
            'type' => $type, 'title' => 'My release', 'metadata' => $metadata,
        ])->assertCreated()->json('data');
    }

    public function test_master_data_lookups_return_active_entries_with_stable_codes(): void
    {
        $this->actingAs($this->artist, 'api')->getJson('/api/catalog/genres')->assertOk()->assertJsonFragment(['code' => 'pop', 'name' => 'Pop', 'is_active' => true, 'sort_order' => 0]);
        $this->getJson('/api/catalog/languages')->assertOk()->assertJsonFragment(['code' => 'id', 'name' => 'Indonesian', 'is_active' => true, 'sort_order' => 0]);
        $this->getJson('/api/catalog/options')->assertOk()->assertJsonStructure(['data' => ['genres', 'languages']]);
        $this->getJson('/api/catalog/releases')->assertOk();
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/genres/pop', ['is_active' => false])->assertOk();
        $this->getJson('/api/admin/catalog/genres')->assertOk()->assertJsonFragment(['code' => 'pop', 'name' => 'Pop', 'is_active' => false, 'sort_order' => 0]);
        $this->actingAs($this->artist, 'api')->getJson('/api/catalog/genres')->assertOk()->assertJsonMissing(['code' => 'pop']);
    }

    public function test_catalog_genres_and_languages_are_validated_and_legacy_names_normalized(): void
    {
        $metadata = $this->metadata();
        $draft = $this->draft($metadata);
        $this->assertSame('pop', $draft['metadata']['primary_genre']);
        $this->assertSame('id', $draft['metadata']['language']);
        $this->assertSame('id', $draft['metadata']['tracks'][0]['language']);
        $metadata['secondary_genre'] = 'unrecognized';
        $metadata['tracks'][0]['language'] = 'unknown';
        $this->patchJson('/api/catalog/releases/'.$draft['id'], ['metadata' => $metadata])->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.secondary_genre', 'metadata.tracks.0.language']);
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/genres/pop', ['is_active' => false])->assertOk();
        $this->actingAs($this->artist, 'api')->postJson('/api/catalog/releases', ['type' => 'music', 'title' => 'Inactive', 'metadata' => ['primary_genre' => 'pop']])->assertUnprocessable()->assertJsonValidationErrors(['metadata.primary_genre']);
    }

    public function test_deactivation_is_rechecked_when_a_saved_draft_is_submitted(): void
    {
        $id = $this->completeDraft()['id'];
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/languages/id', ['is_active' => false])->assertOk();
        $this->actingAs($this->artist, 'api')->postJson("/api/catalog/releases/$id/submit")->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.language', 'metadata.tracks.0.language']);
        $this->assertDatabaseCount('catalog_submissions', 0);
    }

    public function test_master_data_changes_do_not_rewrite_submitted_labels(): void
    {
        $id = $this->completeDraft()['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/genres/pop', ['name' => 'Popular Music'])->assertOk();
        $this->actingAs($this->artist, 'api')->getJson("/api/catalog/releases/$id/submissions/1")->assertOk()
            ->assertJsonFragment(['field' => 'metadata.primary_genre', 'code' => 'pop', 'name' => 'Pop']);
    }

    public function test_master_data_management_requires_admin_and_preserves_codes_and_seed_overrides(): void
    {
        $this->actingAs($this->artist, 'api')->getJson('/api/admin/catalog/languages')->assertForbidden();
        $this->postJson('/api/admin/catalog/genres', ['code' => 'test', 'name' => 'Test'])->assertForbidden();
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/catalog/genres', ['code' => 'shoegaze', 'name' => 'Shoegaze', 'sort_order' => 1])->assertCreated();
        $this->postJson('/api/admin/catalog/genres', ['code' => 'shoegaze', 'name' => 'Another name'])->assertUnprocessable();
        $this->postJson('/api/admin/catalog/genres', ['code' => 'duplicate', 'name' => 'SHOEGAZE'])->assertUnprocessable();
        $this->patchJson('/api/admin/catalog/genres/shoegaze', ['code' => 'other'])->assertUnprocessable();
        $this->patchJson('/api/admin/catalog/languages/missing', ['name' => 'Missing'])->assertNotFound();
        $this->patchJson('/api/admin/catalog/genres/pop', ['is_active' => false, 'name' => 'Popular Music'])->assertOk();
        $this->seed(CatalogMasterDataSeeder::class);
        $this->assertDatabaseHas('catalog_genres', ['code' => 'pop', 'name' => 'Popular Music', 'is_active' => false]);
        $this->assertDatabaseCount('catalog_genres', 23);
    }

    public function test_admin_can_add_dsps_and_options_immediately_use_database_destinations(): void
    {
        $payload = ['code' => 'new_dsp', 'name' => 'New DSP', 'supported_types' => ['music', 'music_video']];
        $this->actingAs($this->artist, 'api')->postJson('/api/admin/catalog/dsps', $payload)->assertForbidden();
        $this->getJson('/api/admin/catalog/dsps')->assertForbidden();
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/catalog/dsps', $payload)->assertCreated()->assertJsonPath('data.supported_types', ['music', 'music_video']);
        $this->postJson('/api/admin/catalog/dsps', $payload)->assertUnprocessable();
        $this->patchJson('/api/admin/catalog/dsps/new_dsp', ['code' => 'changed'])->assertUnprocessable();
        foreach ([[], ['podcast'], ['music', 'music']] as $types) {
            $this->patchJson('/api/admin/catalog/dsps/new_dsp', ['supported_types' => $types])->assertUnprocessable();
        }
        $this->actingAs($this->artist, 'api')->getJson('/api/catalog/dsps')->assertOk()->assertJsonCount(11, 'data')->assertJsonFragment(['code' => 'new_dsp']);
        $options = $this->getJson('/api/catalog/options')->assertOk()->json('data');
        $this->assertContains('new_dsp', $options['music_stores']);
        $this->assertContains('new_dsp', $options['video_stores']);
        $this->draft(['stores' => ['new_dsp']]);
        $this->draft(['stores' => ['new_dsp']], 'music_video');
        foreach (['unknown', 'Spotify', 'apple_music_video'] as $code) {
            $this->postJson('/api/catalog/releases', ['type' => 'music', 'title' => 'Invalid DSP', 'metadata' => ['stores' => [$code]]])->assertUnprocessable()->assertJsonValidationErrors(['metadata.stores.0']);
        }
    }

    public function test_inactive_dsps_are_hidden_and_rejected_and_reseed_preserves_admin_changes(): void
    {
        $id = $this->completeDraft()['id'];
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/dsps/spotify', ['name' => 'Spotify custom', 'is_active' => false])->assertOk();
        $this->seed(CatalogDspSeeder::class);
        $this->assertDatabaseCount('catalog_dsps', 10);
        $this->assertDatabaseHas('catalog_dsps', ['code' => 'spotify', 'name' => 'Spotify custom', 'is_active' => false]);
        $this->getJson('/api/admin/catalog/dsps')->assertOk()->assertJsonFragment(['code' => 'spotify']);
        $this->actingAs($this->artist, 'api')->getJson('/api/catalog/dsps')->assertOk()->assertJsonMissing(['code' => 'spotify']);
        $this->assertNotContains('spotify', $this->getJson('/api/catalog/options')->json('data.music_stores'));
        $this->patchJson("/api/catalog/releases/$id", ['metadata' => ['stores' => ['spotify']]])->assertUnprocessable();
        $this->postJson("/api/catalog/releases/$id/submit")->assertUnprocessable()->assertJsonValidationErrors(['metadata.stores.0']);
    }

    public function test_dsp_supported_type_changes_are_rechecked_at_submission(): void
    {
        $id = $this->completeDraft()['id'];
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/dsps/spotify', ['supported_types' => ['music_video']])->assertOk();
        $this->actingAs($this->artist, 'api')->postJson("/api/catalog/releases/$id/submit")->assertUnprocessable()->assertJsonValidationErrors(['metadata.stores.0']);
        $this->assertDatabaseCount('catalog_submissions', 0);
    }

    public function test_submitted_dsp_labels_survive_admin_renames(): void
    {
        $id = $this->completeDraft()['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/dsps/spotify', ['name' => 'Spotify renamed', 'is_active' => false])->assertOk();
        $this->actingAs($this->artist, 'api')->getJson("/api/catalog/releases/$id/submissions/1")->assertOk()
            ->assertJsonFragment(['field' => 'metadata.stores.0', 'code' => 'spotify', 'name' => 'Spotify']);
    }

    public function test_dsp_logos_can_be_uploaded_and_replaced_only_by_admins(): void
    {
        Storage::fake('public');
        $this->actingAs($this->artist, 'api')->postJson('/api/admin/catalog/dsps/spotify/logo', ['logo' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/catalog/dsps/spotify/logo', ['logo' => UploadedFile::fake()->create('logo.txt', 1, 'text/plain')])->assertUnprocessable();
        $this->postJson('/api/admin/catalog/dsps/spotify/logo', ['logo' => UploadedFile::fake()->image('logo.png')->size(2049)])->assertUnprocessable();
        $this->postJson('/api/admin/catalog/dsps/missing/logo', ['logo' => UploadedFile::fake()->image('logo.png')])->assertNotFound();
        $result = $this->postJson('/api/admin/catalog/dsps/spotify/logo', ['logo' => UploadedFile::fake()->image('logo.png')])->assertOk();
        $old = CatalogDsp::findOrFail('spotify')->logo_path;
        Storage::disk('public')->assertExists($old);
        $this->assertSame(Storage::disk('public')->url($old), $result->json('data.logo_url'));
        $this->postJson('/api/admin/catalog/dsps/spotify/logo', ['logo' => UploadedFile::fake()->image('replacement.png')])->assertOk();
        Storage::disk('public')->assertMissing($old);
        $new = CatalogDsp::findOrFail('spotify')->logo_path;
        Storage::disk('public')->assertExists($new);
        $this->actingAs($this->artist, 'api')->getJson('/api/catalog/dsps')->assertOk()->assertJsonFragment(['logo_url' => Storage::disk('public')->url($new)]);
    }

    private function metadata(): array
    {
        return [
            'release_type' => 'single', 'artists' => [['name' => 'My Artist', 'role' => 'primary']],
            'primary_genre' => 'Pop', 'language' => 'Indonesian',
            'copyright_year' => 2026, 'copyright_owner' => 'My Artist', 'recording_year' => 2026, 'recording_owner' => 'My Artist',
            'release_date' => now()->addMonth()->format('Y-m-d'), 'territories' => ['WORLD'], 'stores' => ['spotify'], 'rights_confirmed' => true,
            'tracks' => [[
                'id' => (string) Str::uuid(), 'title' => 'My track', 'artists' => [['name' => 'My Artist', 'role' => 'primary']],
                'contributors' => [['name' => 'Writer Name', 'role' => 'songwriter']],
                'language' => 'Indonesian', 'explicit' => 'no', 'instrumental' => false, 'ai_usage' => 'none',
            ]],
        ];
    }

    public function test_timezone_lookup_returns_runtime_iana_identifiers_and_validates_release_time(): void
    {
        $count = count(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL));
        $this->actingAs($this->artist, 'api')->getJson('/api/catalog/timezones')->assertOk()->assertJsonCount($count, 'data')
            ->assertJsonPath('data.0.code', 'UTC')->assertJsonFragment(['code' => 'Asia/Jakarta', 'name' => 'Asia / Jakarta']);
        $this->getJson('/api/catalog/options')->assertOk()->assertJsonCount($count, 'data.timezones');
        $draft = $this->draft(['release_time' => '09:00', 'timezone' => 'Asia/Jakarta']);
        $this->assertSame('Asia/Jakarta', $draft['metadata']['timezone']);
        foreach (['+07:00', 'Not/A_Zone', 'asia/jakarta', 'Asia / Jakarta'] as $timezone) {
            $this->postJson('/api/catalog/releases', ['type' => 'music', 'title' => 'Invalid zone', 'metadata' => ['release_time' => '09:00', 'timezone' => $timezone]])->assertUnprocessable()->assertJsonValidationErrors(['metadata.timezone']);
        }
        $this->postJson('/api/catalog/releases', ['type' => 'music', 'title' => 'Missing zone', 'metadata' => ['release_time' => '09:00']])->assertUnprocessable()->assertJsonValidationErrors(['metadata.timezone']);
        $this->postJson('/api/catalog/releases', ['type' => 'music', 'title' => 'Null zone', 'metadata' => ['release_time' => '09:00', 'timezone' => null]])->assertUnprocessable();
        $this->draft(['timezone' => null]);
    }

    public function test_admin_can_manage_timezone_codes_with_raw_or_encoded_slashes(): void
    {
        $this->actingAs($this->artist, 'api')->getJson('/api/admin/catalog/timezones')->assertForbidden();
        $this->patchJson('/api/admin/catalog/timezones/Asia/Jakarta', ['is_active' => false])->assertForbidden();
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/timezones/Asia/Jakarta', ['name' => 'Jakarta', 'sort_order' => 1])->assertOk()->assertJsonPath('data.code', 'Asia/Jakarta');
        $this->patchJson('/api/admin/catalog/timezones/Asia%2FJakarta', ['is_active' => false])->assertOk();
        $this->patchJson('/api/admin/catalog/timezones/America/Argentina/Buenos_Aires', ['sort_order' => 2])->assertOk();
        $this->patchJson('/api/admin/catalog/timezones/Asia/Jakarta', ['code' => 'Asia/Singapore'])->assertUnprocessable();
        $this->patchJson('/api/admin/catalog/timezones/Not/A_Zone', ['name' => 'Missing'])->assertNotFound();
        $this->postJson('/api/admin/catalog/timezones', ['code' => 'Not/A_Zone', 'name' => 'Invalid'])->assertUnprocessable();
        $this->postJson('/api/admin/catalog/timezones', ['code' => 'UTC', 'name' => 'Duplicate'])->assertUnprocessable();
        $this->getJson('/api/admin/catalog/timezones')->assertOk()->assertJsonFragment(['code' => 'Asia/Jakarta', 'name' => 'Jakarta', 'is_active' => false, 'sort_order' => 1]);
    }

    public function test_inactive_timezones_are_rejected_on_save_and_submission_and_preserved_on_reseed(): void
    {
        $metadata = $this->metadata();
        $metadata['release_time'] = '09:00';
        $metadata['timezone'] = 'Asia/Jakarta';
        $id = $this->completeDraft($metadata)['id'];
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/timezones/Asia/Jakarta', ['is_active' => false, 'name' => 'Jakarta custom'])->assertOk();
        $this->seed(CatalogTimezoneSeeder::class);
        $this->assertDatabaseCount('catalog_timezones', count(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL)));
        $this->assertDatabaseHas('catalog_timezones', ['code' => 'Asia/Jakarta', 'is_active' => false, 'name' => 'Jakarta custom']);
        $this->actingAs($this->artist, 'api')->getJson('/api/catalog/timezones')->assertOk()->assertJsonMissing(['code' => 'Asia/Jakarta']);
        $this->patchJson("/api/catalog/releases/$id", ['metadata' => $metadata])->assertUnprocessable()->assertJsonValidationErrors(['metadata.timezone']);
        $this->postJson("/api/catalog/releases/$id/submit")->assertUnprocessable()->assertJsonValidationErrors(['metadata.timezone']);
        $this->assertDatabaseCount('catalog_submissions', 0);
    }

    public function test_timezone_labels_are_frozen_in_submitted_snapshots(): void
    {
        $metadata = $this->metadata();
        $metadata['release_time'] = '09:00';
        $metadata['timezone'] = 'Asia/Jakarta';
        $id = $this->completeDraft($metadata)['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/timezones/Asia/Jakarta', ['name' => 'Jakarta renamed'])->assertOk();
        $this->actingAs($this->artist, 'api')->getJson("/api/catalog/releases/$id/submissions/1")->assertOk()->assertJsonFragment([
            'field' => 'metadata.timezone', 'code' => 'Asia/Jakarta', 'name' => 'Asia / Jakarta',
        ]);
    }

    public function test_territory_master_lists_countries_and_world_and_rejects_invalid_selections(): void
    {
        $this->actingAs($this->artist, 'api')->getJson('/api/catalog/territories')->assertOk()->assertJsonCount(250, 'data')
            ->assertJsonPath('data.0.code', 'WORLD')->assertJsonFragment(['code' => 'ID', 'name' => 'Indonesia']);
        $this->getJson('/api/catalog/options')->assertOk()->assertJsonCount(250, 'data.territories');
        $draft = $this->draft(['territories' => ['ID', 'SG']]);
        $this->assertSame(['ID', 'SG'], $draft['metadata']['territories']);
        foreach ([['ZZ'], ['id'], ['Indonesia'], ['WORLD', 'ID'], ['ID', 'ID']] as $territories) {
            $this->postJson('/api/catalog/releases', ['type' => 'music', 'title' => 'Invalid', 'metadata' => ['territories' => $territories]])->assertUnprocessable();
        }
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/territories/FR', ['name' => 'ZZ'])->assertOk();
        $this->actingAs($this->artist, 'api')->postJson('/api/catalog/releases', ['type' => 'music', 'title' => 'Invalid', 'metadata' => ['territories' => ['ZZ']]])->assertUnprocessable();
    }

    public function test_territory_deactivation_is_enforced_on_save_and_submission_and_survives_reseeding(): void
    {
        $metadata = $this->metadata();
        $metadata['territories'] = ['ID', 'SG'];
        $id = $this->completeDraft($metadata)['id'];
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/territories/ID', ['is_active' => false, 'name' => 'Indonesia custom', 'sort_order' => 10])->assertOk();
        $this->seed(CatalogTerritorySeeder::class);
        $this->assertDatabaseCount('catalog_territories', 250);
        $this->assertDatabaseHas('catalog_territories', ['code' => 'ID', 'name' => 'Indonesia custom', 'is_active' => false, 'sort_order' => 10]);
        $this->getJson('/api/admin/catalog/territories')->assertOk()->assertJsonCount(250, 'data');
        $this->actingAs($this->artist, 'api')->getJson('/api/catalog/territories')->assertOk()->assertJsonMissing(['code' => 'ID']);
        $this->postJson("/api/catalog/releases/$id/submit")->assertUnprocessable()->assertJsonValidationErrors(['metadata.territories.0']);
        $this->patchJson("/api/catalog/releases/$id", ['metadata' => $metadata])->assertUnprocessable();
        $this->assertDatabaseCount('catalog_submissions', 0);
    }

    public function test_territory_names_are_preserved_in_submitted_snapshots(): void
    {
        $metadata = $this->metadata();
        $metadata['territories'] = ['ID'];
        $id = $this->completeDraft($metadata)['id'];
        $this->postJson("/api/catalog/releases/$id/submit")->assertOk();
        $this->actingAs($this->admin, 'api')->patchJson('/api/admin/catalog/territories/ID', ['name' => 'Indonesia renamed'])->assertOk();
        $this->actingAs($this->artist, 'api')->getJson("/api/catalog/releases/$id/submissions/1")->assertOk()->assertJsonFragment([
            'field' => 'metadata.territories.0', 'code' => 'ID', 'name' => 'Indonesia',
        ]);
    }

    public function test_territory_admin_endpoints_require_admin_and_known_immutable_codes(): void
    {
        $this->actingAs($this->artist, 'api')->getJson('/api/admin/catalog/territories')->assertForbidden();
        $this->patchJson('/api/admin/catalog/territories/ID', ['is_active' => false])->assertForbidden();
        $this->postJson('/api/admin/catalog/territories', ['code' => 'ZZ', 'name' => 'Invalid'])->assertForbidden();
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/catalog/territories', ['code' => 'ZZ', 'name' => 'Invalid'])->assertUnprocessable();
        $this->postJson('/api/admin/catalog/territories', ['code' => 'ID', 'name' => 'Duplicate'])->assertUnprocessable();
        $this->patchJson('/api/admin/catalog/territories/ID', ['code' => 'SG'])->assertUnprocessable();
        $this->patchJson('/api/admin/catalog/territories/ID', ['name' => 'Singapore'])->assertUnprocessable();
        $this->patchJson('/api/admin/catalog/territories/WORLD', ['is_active' => false])->assertOk();
        $this->actingAs($this->artist, 'api')->postJson('/api/catalog/releases', ['type' => 'music', 'title' => 'Worldwide unavailable', 'metadata' => ['territories' => ['WORLD']]])->assertUnprocessable();
    }

    private function completeDraft(?array $metadata = null): array
    {
        $metadata ??= $this->metadata();
        $id = $this->draft($metadata)['id'];
        $this->uploadMusic($id, $metadata['tracks'][0]['id']);

        return $this->getJson("/api/catalog/releases/$id")->assertOk()->json('data');
    }

    private function uploadMusic(string $id, string $track): void
    {
        $this->postJson("/api/catalog/releases/$id/assets", [
            'kind' => 'artwork', 'file' => UploadedFile::fake()->image('cover.png', 10, 10),
        ])->assertCreated();
        $this->postJson("/api/catalog/releases/$id/assets", ['kind' => 'audio', 'track_id' => $track, 'file' => $this->audio()])->assertCreated();
    }

    private function audio(): UploadedFile
    {
        $samples = str_repeat("\0", 200);
        $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 44100, 88200, 2, 16).'data'.pack('V', strlen($samples)).$samples;

        return UploadedFile::fake()->createWithContent('track.wav', $wav);
    }

    private function approve(string $id): void
    {
        $this->actingAs($this->admin, 'api')->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'under_review', 'version' => 1])->assertOk();
        $this->postJson("/api/admin/catalog/releases/$id/status", ['status' => 'approved', 'version' => 1])->assertOk();
    }

    private function distributionData(): array
    {
        return ['version' => 1, 'provider' => 'onerpm', 'reference' => 'EXTERNAL-123', 'submitted_at' => now()->subMinute()->toIso8601String()];
    }
}
