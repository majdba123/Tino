<?php

namespace Database\Seeders;

use App\Models\Pet;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PetsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Pet::create([
            'name' => 'بادي',
            'type' => 'كلب',
            'birth_date' => Carbon::now()->subYears(3),
            'gender' => 'male',
            'name_cheap' => '321321321',
            'breed' => 'ssas',
            'health_status' => 'excellent',
            'status' => 'active',
            'user_id' => 2 // ID المستخدم الإداري
        ]);

        Pet::create([
            'name' => 'ميسي',
            'type' => 'قطة',
            'birth_date' => Carbon::now()->subYears(1),
            'gender' => 'male',
            'name_cheap' => '321321321',
            'breed' => 'ssas',
            'health_status' => 'excellent',
            'status' => 'active',
            'user_id' => 2 // ID المستخدم الإداري
        ]);
    }
}
