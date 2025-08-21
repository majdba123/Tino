<?php

namespace App\Models;

 use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
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
        'image',
        'email',
        'payment_methods',
        'otp',
        'status',
        'type',
        'password',
        'email_verified_at',
        'stripe_customer_id',
        'stripe_payment_method_id',
            'date_of_birth',
            'gender',
            'country',
            'city',
            'street',
            'address',
            'apartment',
            'postal_code',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_email',
            'communication_preference',

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


    public function employee()
    {
        return $this->hasOne(Employee::class);
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


    public function contact()
    {
        return $this->hasMany(Contact::class);
    }


    public function user_review()
    {
        return $this->hasMany(User_Review::class);
    }



    public function user_notification()
    {
        return $this->hasMany(User_Notification::class);
    }

    public function Refound()
    {
        return $this->hasMany(Refound::class);
    }


}
