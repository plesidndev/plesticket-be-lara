<?php

namespace Tests\Feature;

use App\Models\IdempotencyKey;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TalentCreateIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creator = User::create(['uid' => 'U000001', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);
    }

    /** @return array<string, string> */
    private function fields(): array
    {
        return ['name' => 'Sunset Band', 'type' => 'group', 'category' => 'band'];
    }

    public function test_repeating_a_submission_with_the_same_key_creates_one_talent(): void
    {
        $first = $this->actingAs($this->creator, 'api')
            ->withHeader('Idempotency-Key', 'form-token-1')
            ->postJson('/api/talents', $this->fields())->assertCreated();

        $second = $this->withHeader('Idempotency-Key', 'form-token-1')
            ->postJson('/api/talents', $this->fields())->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Talent::count());
    }

    public function test_a_different_key_creates_a_second_talent(): void
    {
        $this->actingAs($this->creator, 'api')->withHeader('Idempotency-Key', 'form-token-1')->postJson('/api/talents', $this->fields())->assertCreated();
        $this->withHeader('Idempotency-Key', 'form-token-2')->postJson('/api/talents', $this->fields())->assertCreated();

        $this->assertSame(2, Talent::count());
    }

    public function test_a_key_is_scoped_to_the_caller_who_used_it(): void
    {
        $other = User::create(['uid' => 'U000002', 'name' => 'Sari', 'email' => 'sari@example.com', 'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true]);

        $this->actingAs($this->creator, 'api')->withHeader('Idempotency-Key', 'shared')->postJson('/api/talents', $this->fields())->assertCreated();
        $this->actingAs($other, 'api')->withHeader('Idempotency-Key', 'shared')->postJson('/api/talents', $this->fields())->assertCreated();

        $this->assertSame(2, Talent::count());
        $this->assertSame(1, Talent::where('submitted_by', $this->creator->id)->count());
    }

    /**
     * The real failure this feature exists for: a second request arriving while the first is still
     * creating. Pre-claiming the key without a resource_id is exactly the state the database is in
     * mid-flight, so the second caller must be refused rather than allowed to create a duplicate.
     */
    public function test_a_request_arriving_mid_flight_is_refused_rather_than_duplicating(): void
    {
        IdempotencyKey::create(['scope' => 'talents.create', 'user_id' => $this->creator->id, 'key' => 'in-flight']);

        $this->actingAs($this->creator, 'api')->withHeader('Idempotency-Key', 'in-flight')
            ->postJson('/api/talents', $this->fields())->assertStatus(409);

        $this->assertSame(0, Talent::count());
    }

    public function test_a_failed_creation_hands_the_key_back_so_the_caller_can_retry(): void
    {
        $this->actingAs($this->creator, 'api')->withHeader('Idempotency-Key', 'retry-me')
            ->postJson('/api/talents', ['name' => 'Sunset Band', 'type' => 'group', 'category' => 'no-such-category'])
            ->assertStatus(422);

        $this->assertSame(0, IdempotencyKey::count());

        $this->withHeader('Idempotency-Key', 'retry-me')->postJson('/api/talents', $this->fields())->assertCreated();
        $this->assertSame(1, Talent::count());
    }

    public function test_a_request_without_the_header_behaves_as_before(): void
    {
        $this->actingAs($this->creator, 'api')->postJson('/api/talents', $this->fields())->assertCreated();
        $this->postJson('/api/talents', $this->fields())->assertCreated();

        $this->assertSame(2, Talent::count());
        $this->assertSame(0, IdempotencyKey::count());
    }

    public function test_an_overlong_key_is_refused_before_anything_is_created(): void
    {
        $this->actingAs($this->creator, 'api')->withHeader('Idempotency-Key', str_repeat('k', 101))
            ->postJson('/api/talents', $this->fields())->assertStatus(422);

        $this->assertSame(0, Talent::count());
    }
}
