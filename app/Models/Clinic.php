<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'phone',
        'latitude',
        'longitude',
        'opening_time',
        'closing_time',
        'type',
        'status',
        'user_id',
        'tax_number',
        'bank_account_info',
        'payment_terms'

    ];

    protected $casts = [
        'opening_time' => 'datetime:H:i',
        'closing_time' => 'datetime:H:i',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }



    public function medicalRecords()
    {
        return $this->morphMany(MedicalRecord::class, 'recordable');
    }

    public function anwer_cons()
    {
        return $this->hasMany(Anwer_Cons::class);
    }
    public function Order_Clinic()
    {
        return $this->hasMany(Order_Clinic::class);
    }


    public function user_review()
    {
        return $this->hasMany(User_Review::class);
    }


}
