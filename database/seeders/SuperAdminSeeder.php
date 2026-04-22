<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'superadmin@plesticket.com'],
            [
                'name'      => 'Super Admin',
                'password'  => bcrypt('adminpass'),
                'phone'     => null,
                'role'      => UserRole::SuperAdmin,
                'is_active' => true,
            ]
        );

        if (! $user->uid) {
            $user->update(['uid' => sprintf('SA%04d', $user->id)]);
        }
    }
}
