<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order_Clinic extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'clinic_id',
        'clinic_note',
        'status',
    ];




    public function consultation()
    {
        return $this->belongsTo(Consultation::class, "consultation_id");
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, "clinic_id");
    }
}
