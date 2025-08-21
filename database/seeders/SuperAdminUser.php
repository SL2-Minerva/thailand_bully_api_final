<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminUser extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'Super admin',
            'email' => 'admin_007@test.com',
            'password' => Hash::make('456123'),
            'role_id' => 1,
            'organization_id' => 1,
            'is_admin' => 1,
            'status' => 1
        ]);
    }
}
