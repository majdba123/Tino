<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pet_id',
        'description',
        'admin_notes',
        'operation',
        'status',
        'level_urgency',
        'contact_method',
        'type_con',
        'data_available',


    ];

    const STATUS_PENDING = 'pending';
    const STATUS_REVIEWED = 'reviewed';
    const STATUS_CLOSED = 'complete';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function anwer_cons()
    {
        return $this->hasOne(Anwer_Cons::class);
    }

    public function Order_Clinic()
    {
        return $this->hasMany(Order_Clinic::class);
    }


    public function completedOrderWithInvoice()
    {
        return $this->hasOne(Order_Clinic::class)
                    ->where('status', 'complete')
                    ->with(['pill'])
                    ->latest(); // للحصول على أحدث طلب مكتمل
    }

}
