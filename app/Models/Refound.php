<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refound extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_subscription_id',
        'status',
    ];


    public function user()
    {
        return $this->belongsTo(User::class ,'user_id');
    }
    public function User_Subscription()
    {
        return $this->belongsTo(User_Subscription::class ,'user_subscription_id');
    }

}
