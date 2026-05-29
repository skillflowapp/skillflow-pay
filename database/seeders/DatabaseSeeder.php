<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'SkillFlow Admin',
            'email' => 'admin@pay.skillflowtz.com',
            'password' => bcrypt('admin123#'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
    }
}
