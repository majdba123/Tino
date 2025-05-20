<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pill extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_clinic_id',
        'medical_details',
        'clinic_note',
        'price_order',
        'have_discount',
        'discount_percent',
        'discount_amount',
        'final_price',
        'issued_at'
    ];

    protected $casts = [
        'have_discount' => 'boolean',
        'issued_at' => 'datetime'
    ];

    public function orderClinic()
    {
        return $this->belongsTo(Order_Clinic::class, 'order_clinic_id');
    }
}
