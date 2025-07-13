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
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'type' => "admin",
                        'email_verified_at' => now(), // إضافة هذا السطر
                        'otp' => 1, // إضافة هذا السطر


        ]);



        User::create([
            'id' => 2,
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'type' => 0,
                        'email_verified_at' => now(), // إضافة هذا السطر
                        'otp' => 1, // إضافة هذا السطر


        ]);



        User::create([
            'id' => 3,
            'name' => 'clinic',
            'email' => 'clinic@example.com',
            'password' => Hash::make('password'),
            'type' => 3,
                        'email_verified_at' => now(), // إضافة هذا السطر
                        'otp' => 1, // إضافة هذا السطر


        ]);

        User::create([
            'id' => 4,
            'name' => 'clinic',
            'email' => 'clinic1@example.com',
            'password' => Hash::make('password'),
            'type' => 3,
                        'email_verified_at' => now(), // إضافة هذا السطر
                        'otp' => 1, // إضافة هذا السطر


        ]);



    }
}
