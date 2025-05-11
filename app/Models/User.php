<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'google_id',
        'facebook_id',
        'phone',
        'email',
        'otp',
        'type',
        'password',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    public function User_Subscription()
    {
        return $this->hasMany(User_Subscription::class);
    }


    public function clinic()
    {
        return $this->hasOne(Clinic::class);
    }



    public function pets()
    {
        return $this->hasMany(Pet::class);
    }

    public function discountCoupons()
    {
        return $this->hasMany(DiscountCoupon::class);
    }

    public function medicalRecords()
    {
        return $this->morphMany(MedicalRecord::class, 'recordable');
    }

    // أضف هذه العلاقة إلى نموذج User
    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }



}
