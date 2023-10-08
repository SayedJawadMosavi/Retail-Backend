<?php

namespace Database\Seeders;

use App\Models\User;


use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $permissions = [
            'user_view', 'user_create', 'user_delete', 'user_restore',
           
        ];
        User::create(
            [
                'name' => 'admin',
                'email' => 'admin@admin.com',
                'email_verified_at' => now(),
                "role" => 'admin',
                'permissions' => json_encode($permissions),
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'remember_token' => '12',
            ]
        );
        // Customer::factory(10)->create();
    
    }
}
