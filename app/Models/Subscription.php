<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'duration_months',
        'type',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    // علاقة مع المستخدمين الذين اشتركوا في هذه الخطة
    public function users()
    {
        return $this->hasMany(User_Subscription::class);
    }

    // نطاق للاشتراكات النشطة فقط
// نطاق للفلترة حسب حالة النشاط
    public function scopeIsActive($query, $value)
    {
        if ($value === 'all') {
            return $query;
        }

        return $query->where('is_active', (bool)$value);
    }
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }




}
