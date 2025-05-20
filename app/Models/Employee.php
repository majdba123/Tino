<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'clinic_id',
        'position',
        'status'
    ];

    protected $attributes = [
        'status' => 'active'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
