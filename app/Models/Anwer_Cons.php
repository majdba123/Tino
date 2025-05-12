<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anwer_Cons extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'clinic_info', // تغيير من clinic_id إلى clinic_info
        'operation' // إضافة الحقل الجديد
    ];




    public function consultation()
    {
        return $this->belongsTo(Consultation::class, "consultation_id");
    }



}
