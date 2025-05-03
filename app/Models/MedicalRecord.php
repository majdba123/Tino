<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $fillable = ['details', 'date', 'pet_id', 'recordable_id', 'recordable_type'];

    public function recordable()
    {
        return $this->morphTo();
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }
}
