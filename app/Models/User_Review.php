<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User_Review extends Model
{
    use HasFactory;

      protected $fillable = [
        'user_id',
        'clinic_id',
        'status',
        'review',
        'rating',

    ];



    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
