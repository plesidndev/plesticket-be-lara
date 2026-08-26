<?php

namespace Database\Seeders;

use App\Enums\IdentityType;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyEventSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::where('email', 'superadmin@plesticket.com')->firstOrFail();

            $organizer = User::updateOrCreate(
                ['email' => 'organizer.demo@plesticket.com'],
                [
                    'uid' => 'USR-DEMO-ORG',
                    'name' => 'Ples Demo Organizer',
                    'username' => 'ples_demo_organizer',
                    'phone' => '081234567890',
                    'password' => 'password123',
                    'role' => UserRole::RegisteredUser,
                    'is_organizer' => true,
                    'is_active' => true,
                ],
            );

            User::updateOrCreate(
                ['email' => 'buyer.demo@plesticket.com'],
                [
                    'uid' => 'USR-DEMO-BUYER',
                    'name' => 'Ples Demo Buyer',
                    'username' => 'ples_demo_buyer',
                    'phone' => '081298765432',
                    'date_of_birth' => '1995-05-15',
                    'password' => 'password123',
                    'role' => UserRole::RegisteredUser,
                    'is_active' => true,
                ],
            );

            $events = [
                [
                    'event_id' => 'EVT9001',
                    'title' => 'Jakarta September Music Fest 2026',
                    'slug' => 'jakarta-september-music-fest-2026',
                    'description' => 'A full-day music festival featuring local artists and bands.',
                    'category' => 'Music',
                    'start_date' => '2026-09-05',
                    'end_date' => '2026-09-05',
                    'start_time' => '15:00:00',
                    'end_time' => '23:00:00',
                    'venue_name' => 'Lapangan Banteng',
                    'address' => 'Pasar Baru, Sawah Besar',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'latitude' => -6.1701000,
                    'longitude' => 106.8355000,
                    'tickets' => [
                        ['name' => 'Festival Pass', 'description' => 'General festival admission.', 'price' => 150000, 'quota' => 500],
                        ['name' => 'VIP Pass', 'description' => 'Priority entry and VIP viewing area.', 'price' => 450000, 'quota' => 100],
                    ],
                ],
                [
                    'event_id' => 'EVT9002',
                    'title' => 'Bandung Creative Technology Conference 2026',
                    'slug' => 'bandung-creative-technology-conference-2026',
                    'description' => 'Talks and workshops for developers, designers, and digital creators.',
                    'category' => 'Technology',
                    'start_date' => '2026-09-12',
                    'end_date' => '2026-09-13',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'venue_name' => 'Bandung Convention Centre',
                    'address' => 'Jl. Soekarno Hatta, Bandung',
                    'city' => 'Kota Bandung',
                    'province' => 'Jawa Barat',
                    'latitude' => -6.9291000,
                    'longitude' => 107.6298000,
                    'tickets' => [
                        ['name' => 'Conference Pass', 'description' => 'Access to all conference sessions.', 'price' => 250000, 'quota' => 300],
                        ['name' => 'Conference + Workshop', 'description' => 'Conference access plus one workshop.', 'price' => 500000, 'quota' => 80],
                    ],
                ],
                [
                    'event_id' => 'EVT9003',
                    'title' => 'Surabaya Food and Coffee Expo 2026',
                    'slug' => 'surabaya-food-coffee-expo-2026',
                    'description' => 'Discover Indonesian food brands, coffee roasters, and live demonstrations.',
                    'category' => 'Food & Drink',
                    'start_date' => '2026-09-19',
                    'end_date' => '2026-09-20',
                    'start_time' => '10:00:00',
                    'end_time' => '21:00:00',
                    'venue_name' => 'Grand City Convention Hall',
                    'address' => 'Jl. Walikota Mustajab No. 1, Surabaya',
                    'city' => 'Kota Surabaya',
                    'province' => 'Jawa Timur',
                    'latitude' => -7.2611000,
                    'longitude' => 112.7505000,
                    'tickets' => [
                        ['name' => 'Daily Entry', 'description' => 'Valid for one expo day.', 'price' => 50000, 'quota' => 1000],
                        ['name' => 'Weekend Pass', 'description' => 'Valid for both expo days.', 'price' => 85000, 'quota' => 400],
                    ],
                ],
                [
                    'event_id' => 'EVT9004',
                    'title' => 'Yogyakarta Art Weekend 2026',
                    'slug' => 'yogyakarta-art-weekend-2026',
                    'description' => 'A weekend of exhibitions, performances, and conversations with artists.',
                    'category' => 'Arts & Culture',
                    'start_date' => '2026-09-26',
                    'end_date' => '2026-09-27',
                    'start_time' => '10:00:00',
                    'end_time' => '20:00:00',
                    'venue_name' => 'Jogja National Museum',
                    'address' => 'Jl. Prof. Ki Amri Yahya No. 1, Yogyakarta',
                    'city' => 'Kota Yogyakarta',
                    'province' => 'DI Yogyakarta',
                    'latitude' => -7.8003000,
                    'longitude' => 110.3531000,
                    'tickets' => [
                        ['name' => 'General Admission', 'description' => 'Access to exhibitions and performances.', 'price' => 75000, 'quota' => 600],
                        ['name' => 'Supporter Pass', 'description' => 'Admission plus event merchandise.', 'price' => 200000, 'quota' => 150],
                    ],
                ],
                [
                    'event_id' => 'EVT9005',
                    'title' => 'Indonesia Online Business Summit 2026',
                    'slug' => 'indonesia-online-business-summit-2026',
                    'description' => 'A live online summit about entrepreneurship, marketing, and business growth.',
                    'category' => 'Business & Finance',
                    'start_date' => '2026-10-03',
                    'end_date' => '2026-10-03',
                    'start_time' => '09:00:00',
                    'end_time' => '16:00:00',
                    'is_online' => true,
                    'tickets' => [
                        ['name' => 'Livestream Access', 'description' => 'Full access to the live online summit.', 'price' => 100000, 'quota' => 2000],
                        ['name' => 'Premium Access', 'description' => 'Livestream, recordings, and digital materials.', 'price' => 275000, 'quota' => 500],
                    ],
                ],
            ];

            foreach ($events as $eventData) {
                $ticketTypes = $eventData['tickets'];
                unset($eventData['tickets']);

                $event = Event::updateOrCreate(
                    ['slug' => $eventData['slug']],
                    array_merge([
                        'user_id' => $organizer->id,
                        'banner_url' => null,
                        'pic_name' => 'Ples Demo Organizer',
                        'pic_identity_type' => IdentityType::Ktp,
                        'pic_identity_number' => '3173000000000001',
                        'pic_npwp' => '01.234.567.8-901.000',
                        'is_online' => false,
                        'venue_name' => null,
                        'address' => null,
                        'city' => null,
                        'province' => null,
                        'latitude' => null,
                        'longitude' => null,
                        'verification_status' => VerificationStatus::Verified,
                        'rejection_reason' => null,
                        'verified_at' => now(),
                        'verified_by' => $admin->id,
                        'show_status' => true,
                        'is_published' => true,
                    ], $eventData),
                );

                foreach ($ticketTypes as $ticketType) {
                    $event->ticketTypes()->updateOrCreate(
                        ['name' => $ticketType['name']],
                        array_merge($ticketType, [
                            'is_active' => true,
                            'sale_start' => '2026-08-01 00:00:00',
                            'sale_end' => $eventData['start_date'] . ' 23:59:59',
                        ]),
                    );
                }
            }
        });
    }
}
