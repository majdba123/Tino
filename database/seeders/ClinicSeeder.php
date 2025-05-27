<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ClinicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // الحصول على بعض المستخدمين لربطهم بالعيادات

        // عيادات خارجية
        Clinic::create([
            'address' => 'شارع الملك عبدالعزيز، الرياض',
            'phone' => '0112345678',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'opening_time' => Carbon::createFromTime(8, 0, 0),
            'closing_time' => Carbon::createFromTime(22, 0, 0),
            'type' => 'integrated',
            'tax_number' => 'tax_number',
            'bank_account_info' => 'bank_account_info',
            'payment_terms' => 'payment_terms',

            'status' => 'active',
            'user_id' => 3
        ]);

        Clinic::create([
            'address' => 'حي العليا، جدة',
            'phone' => '0123456789',
            'latitude' => 21.5433,
            'longitude' => 39.1728,
            'opening_time' => Carbon::createFromTime(9, 0, 0),
            'closing_time' => Carbon::createFromTime(21, 0, 0),
            'type' => 'external',
                        'tax_number' => 'tax_number',
            'bank_account_info' => 'bank_account_info',
            'payment_terms' => 'payment_terms',
            'status' => 'active',
            'user_id' =>4
        ]);


    }
}
