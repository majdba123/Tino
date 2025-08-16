<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactReply extends Model
{
    use HasFactory;
    protected $fillable = [
        'contact_id',
        'message',
    ];


    public function Contact()
    {
        return $this->belongsTo(Contact::class , 'contact_id');
    }
}
