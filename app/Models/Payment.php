<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

      protected $fillable = [
        'user_id',
        'user_subscription_id',
        'payment_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'details'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'details' => 'array'
    ];

    // العلاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userSubscription()
    {
        return $this->belongsTo(User_Subscription::class,'user_subscription_id');
    }

    // حالات الدفع
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
}
