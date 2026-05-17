<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Reyhan',
                'first_name' => 'Reyhan',
                'last_name' => 'Hamami',
                'job_title' => 'Fullstack developer',
                'phone' => '+09 363 398 46',
                'bio' => 'Fullstack developer',
                'facebook_url' => 'https://facebook.com/reyhanhamami',
                'x_url' => 'https://x.com/reyhanhamami',
                'linkedin_url' => 'https://linkedin.com/reyhanhamami',
                'instagram_url' => 'https://instagram.com/reyhanhamami',
                'dribbble_url' => 'https://dribbble.com/reyhanhamami',
                'country' => 'Indonesia',
                'city_state' => 'Depok',
                'postal_code' => '2489',
                'tax_id' => '-',
                'password' => bcrypt('asddsa123'),
            ]
        );
    }
}
