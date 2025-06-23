<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User_Subscription extends Model
{
    use HasFactory;


    const STATUS_PENDING = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_EXPIRED = 2;
    const STATUS_FAILED = 3;


    protected $fillable = [
        'user_id',
        'subscription_id',
        'start_date',
        'end_date',
        'remaining_calls',
        'remaining_visits',
        'price_paid',
        'is_active',


        'payment_method',
        'payment_status',
        'payment_session_id'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('end_date', '>=', now());
    }


    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
