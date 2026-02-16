<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $chunkSize = 1000; // Insert 1000 per batch
        $totalUsers = 10000;
        
        $this->command->info("Creating {$totalUsers} users...");
        
        // Bulk insert ke database
        for ($i = 0; $i < $totalUsers; $i += $chunkSize) {
            $users = [];
            $currentChunk = min($chunkSize, $totalUsers - $i);
            
            for ($j = 0; $j < $currentChunk; $j++) {
                $users[] = [
                    'name' => fake()->name(),
                    'email' => fake()->unique()->safeEmail(),
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'remember_token' => Str::random(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            // Bulk insert
            User::insert($users);
            
            $this->command->info("Created " . ($i + $currentChunk) . " users in database...");
        }
        
        $this->command->info("Done inserting to database!");
        $this->command->info("Now syncing to Meilisearch...");
        
        // Sync semua ke Meilisearch menggunakan artisan command
        Artisan::call('scout:import', [
            'model' => User::class
        ]);
        
        $this->command->info("Done! All {$totalUsers} users are now indexed in Meilisearch.");
    }
}
