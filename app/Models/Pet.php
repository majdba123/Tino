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
        'user_id'
    ];

    protected $casts = [
        'birth_date' => 'date',
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
}
