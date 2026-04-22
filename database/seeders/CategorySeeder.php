<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Music',
            'Sports',
            'Food & Drink',
            'Arts & Culture',
            'Technology',
            'Business & Finance',
            'Education',
            'Entertainment',
            'Festival',
            'Conference',
            'Exhibition',
            'Comedy',
            'Film & Cinema',
            'Fashion',
            'Health & Wellness',
            'Gaming',
            'Travel & Outdoor',
            'Charity & Community',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
