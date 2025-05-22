<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'status', // 'open', 'pending', 'resolved', 'closed'
        'user_id',

    ];

    /**
     * Get the parent contactable model (user or vendor).
     */
    public function user()
    {
        return $this->belongsTo(User::class , 'user_id');
    }

    /**
     * Get all replies for this contact.
     */
    public function replies()
    {
        return $this->hasMany(ContactReply::class);
    }


}
