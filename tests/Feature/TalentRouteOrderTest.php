<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TalentRouteOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * /talents/mine must not be swallowed by /talents/{id}. When it is,
     * TalentController::show() receives the string "mine" for its int $id and
     * throws a TypeError, surfacing as a 500.
     */
    public function test_talents_mine_resolves_to_the_mine_action(): void
    {
        $eo = User::create([
            'uid' => 'USR0001', 'name' => 'EO', 'email' => 'eo@example.com',
            'password' => 'secret', 'role' => 'REGISTERED_USER', 'is_organizer' => true,
        ]);

        $this->actingAs($eo, 'api')->getJson('/api/talents/mine')->assertOk();
    }

    public function test_talents_mine_is_still_gated_to_organizers(): void
    {
        $buyer = User::create([
            'uid' => 'USR0002', 'name' => 'Buyer', 'email' => 'buyer@example.com',
            'password' => 'secret', 'role' => 'REGISTERED_USER',
        ]);

        $this->actingAs($buyer, 'api')->getJson('/api/talents/mine')
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'EO_NOT_ACTIVATED');
    }

    public function test_a_numeric_talent_id_still_reaches_show(): void
    {
        // 404 (not found) rather than 500 (TypeError) proves routing is intact.
        $this->getJson('/api/talents/999')->assertStatus(404);
    }
}
