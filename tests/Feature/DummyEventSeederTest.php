<?php

namespace Tests\Feature;

use App\Enums\OrganizerRole;
use App\Models\OrganizerMember;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DummyEventSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_demo_eo_accounts_and_events_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'organizer.demo@plesticket.com')->firstOrFail();

        $this->assertTrue($owner->is_organizer);
        $this->assertTrue(Hash::check('password123', $owner->password));
        $this->assertDatabaseCount('events', 5);

        $staff = OrganizerMember::where('uid', 'EVT9001-STF-0001')->firstOrFail();

        $this->assertSame($owner->id, $staff->owner_id);
        $this->assertSame(OrganizerRole::EoStaff, $staff->role);
        $this->assertTrue($staff->is_active);
        $this->assertTrue(Hash::check('password123', $staff->password));
        $this->assertDatabaseCount('organizer_members', 1);
    }
}
