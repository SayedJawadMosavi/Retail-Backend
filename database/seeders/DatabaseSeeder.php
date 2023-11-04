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
            'user_view', 'user_create', 'user_delete', 'user_restore','user_force_delete',
            'employee_view', 'employee_create', 'employee_delete', 'employee_restore','employee_force_delete',
            'salaries_view', 'salaries_create', 'employee_delete', 'employee_restore','employee_force_delete',
            'customer_view', 'customer_create', 'employee_delete', 'employee_restore','employee_force_delete',
            'income_expense_view', 'income_expense_create', 'employee_delete', 'employee_restore','employee_force_delete',
            'product_view', 'product_create', 'employee_delete', 'employee_restore','employee_force_delete',
            'purchase_view', 'purchase_create', 'employee_delete', 'employee_restore','employee_force_delete',
            'sell_view', 'sell_create', 'employee_delete', 'employee_restore','employee_force_delete',
            'stock_product_transfer_view', 'stock_product_transfer_create', 'employee_delete', 'employee_restore','employee_force_delete',
            'stock_to_stock_transfer_view', 'stock_to_stock_transfer_create', 'employee_delete', 'employee_restore','employee_force_delete',
            'stock_view', 'stock_create', 'employee_delete', 'employee_restore','employee_force_delete',
            'vendor_view', 'vendor_create', 'employee_delete', 'employee_restore','employee_force_delete',

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
