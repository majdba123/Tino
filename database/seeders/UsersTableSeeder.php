<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        User::create([
            'id' => 1,
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'type' => 0,
        ]);

        User::create([
            'id' => 2,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'type' => "admin",
        ]);

        User::create([
            'id' => 3,
            'name' => 'clinic',
            'email' => 'clinic@example.com',
            'password' => Hash::make('password'),
            'type' => 3,
        ]);

        User::create([
            'id' => 4,
            'name' => 'clinic',
            'email' => 'clinic1@example.com',
            'password' => Hash::make('password'),
            'type' => 3,
        ]);



    }
}
