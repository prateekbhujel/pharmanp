<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (app()->environment('testing')) {
            User::factory()->create([
                'name' => 'Test Owner',
                'email' => 'owner@example.test',
                'is_owner' => true,
            ]);
        }
    }
}
