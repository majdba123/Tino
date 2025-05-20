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
        'price_order',       // السعر الأصلي للطلب
        'have_discount',     // هل يوجد خصم (true/false)
        'discount_percent',  // نسبة الخصم (0-100)
        'discount_amount',   // مبلغ الخصم المحسوب
        'final_price',       // السعر النهائي بعد الخصم
    ];




    public function consultation()
    {
        return $this->belongsTo(Consultation::class, "consultation_id");
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, "clinic_id");
    }

    // In Order_Clinic.php model
    public function pill()
    {
        return $this->hasOne(Pill::class, 'order_clinic_id');
    }
}
