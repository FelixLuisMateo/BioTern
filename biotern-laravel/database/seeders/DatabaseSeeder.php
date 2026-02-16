<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create an initial admin user for local development/testing
        if (app()->environment('local') || app()->environment() === null) {
            $email = 'admin@biotern.com';
            $exists = \App\Models\User::where('email', $email)->exists();
            if (! $exists) {
                \App\Models\User::create([
                    'name' => 'Admin User',
                    'email' => $email,
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                ]);
                echo "Created admin user: {$email} with password 'password'.\n";
            }
        }
    }
}
