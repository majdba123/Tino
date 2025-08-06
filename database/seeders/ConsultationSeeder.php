<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\User;
use App\Models\Pet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ConsultationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

            Consultation::create([
                'user_id' => 2,
                'pet_id' => 1,
                'description' => 'وصف الاستشارة ' . Str::random(20),
                'operation' => "none",
                'status' =>"pending",
                'level_urgency' =>"pending",
                'contact_method' =>"pending",
                'type_con' =>"pending",
                'data_available' =>"pending",


            ]);

        // يمكن إضافة المزيد من الاستشارات باستخدام Factory إذا لزم الأمر
    }
}
