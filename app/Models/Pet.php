<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'birth_date',
        'image',
        'gender',
        'name_cheap',
        'breed',
        'health_status',
        'status',
        'user_id',
        // الحقول الجديدة
        'color',
        'weight',
        'is_spayed',
        'allergies',
        'previous_surgeries',
        'chronic_conditions',
        'vaccination_history',
        'last_veterinary_visit',
        'current_veterinary',
        'insurance_company',
        'policy_number',
        'coverage_details'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'last_veterinary_visit' => 'date',
        'is_spayed' => 'boolean',
        'allergies' => 'array',
        'previous_surgeries' => 'array',
        'chronic_conditions' => 'array',
        'vaccination_history' => 'array',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    // دالة لحساب العمر من تاريخ الميلاد
    public function getAgeAttribute()
    {
        return $this->birth_date ? $this->birth_date->diffInYears(now()) : null;
    }

    // أضف هذه العلاقة إلى نموذج User
    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }


    public function User_Subscription()
    {
        return $this->hasOne(User_Subscription::class);
    }


    public function user_subscriptionn()
    {
        return $this->hasOne(User_Subscription::class, 'pet_id')->where('is_active', true);
    }


      public function getAllergiesAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function getPreviousSurgeriesAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function getChronicConditionsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function getVaccinationHistoryAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

}
