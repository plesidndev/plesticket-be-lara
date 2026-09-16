<?php

namespace Tests\Feature;

use App\Models\Talent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TalentMediaLinksTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // The band category ships with the schema, so tests use it rather than seeding their own.
        $this->owner = User::create(['uid' => 'U000001', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
    }

    private function fields(array $overrides = []): array
    {
        return array_replace(['name' => 'Senja', 'type' => 'group', 'category' => 'band'], $overrides);
    }

    public function test_a_talent_keeps_its_single_link_and_video_list(): void
    {
        $videos = ['https://www.youtube.com/watch?v=abcdefghijk', 'https://youtu.be/abcdefghijk'];

        $id = $this->actingAs($this->owner, 'api')->postJson('/api/talents', $this->fields([
            'single_url' => 'https://open.spotify.com/track/abc',
            'youtube_videos' => $videos,
        ]))->assertCreated()
            ->assertJsonPath('data.single_url', 'https://open.spotify.com/track/abc')
            ->assertJsonPath('data.youtube_videos', $videos)
            ->json('data.id');

        // Order is part of the value, so it survives the round trip as sent.
        $this->assertSame($videos, Talent::find($id)->youtube_videos);
    }

    public function test_a_talent_without_media_reports_an_empty_list_rather_than_null(): void
    {
        $this->actingAs($this->owner, 'api')->postJson('/api/talents', $this->fields())
            ->assertCreated()
            ->assertJsonPath('data.single_url', null)
            ->assertJsonPath('data.youtube_videos', []);
    }

    public function test_the_video_box_only_accepts_youtube_links(): void
    {
        $this->actingAs($this->owner, 'api')->postJson('/api/talents', $this->fields([
            'youtube_videos' => ['https://vimeo.com/123456'],
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['youtube_videos.0']]);

        $this->actingAs($this->owner, 'api')->postJson('/api/talents', $this->fields([
            'single_url' => 'not-a-link',
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['single_url']]);
    }

    public function test_the_video_box_is_capped(): void
    {
        $this->actingAs($this->owner, 'api')->postJson('/api/talents', $this->fields([
            'youtube_videos' => array_fill(0, 11, 'https://youtu.be/abcdefghijk'),
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['youtube_videos']]);
    }

    public function test_an_update_replaces_the_video_list_and_can_clear_it(): void
    {
        $talent = Talent::create($this->fields([
            'slug' => 'senja', 'submitted_by' => $this->owner->id,
            'youtube_videos' => ['https://youtu.be/aaaaaaaaaaa'],
        ]));

        $this->actingAs($this->owner, 'api')->putJson('/api/talents/'.$talent->id, [
            'youtube_videos' => ['https://youtu.be/bbbbbbbbbbb', 'https://www.youtube.com/watch?v=ccccccccccc'],
        ])->assertOk()->assertJsonCount(2, 'data.youtube_videos');

        $this->actingAs($this->owner, 'api')->putJson('/api/talents/'.$talent->id, ['youtube_videos' => []])
            ->assertOk()->assertJsonPath('data.youtube_videos', []);
    }

    public function test_an_update_that_mentions_neither_field_leaves_them_alone(): void
    {
        $talent = Talent::create($this->fields([
            'slug' => 'senja', 'submitted_by' => $this->owner->id,
            'single_url' => 'https://open.spotify.com/track/abc',
            'youtube_videos' => ['https://youtu.be/aaaaaaaaaaa'],
        ]));

        $this->actingAs($this->owner, 'api')->putJson('/api/talents/'.$talent->id, ['genre' => 'Pop'])
            ->assertOk()
            ->assertJsonPath('data.single_url', 'https://open.spotify.com/track/abc')
            ->assertJsonCount(1, 'data.youtube_videos');
    }
}
