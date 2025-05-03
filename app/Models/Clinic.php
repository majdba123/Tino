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
        'user_id'
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
}
