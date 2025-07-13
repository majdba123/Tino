<?php

namespace App\Services\registartion;

use App\Models\Afiliate;
use App\Models\User;
use App\Models\Driver;
use App\Models\vendor;
use DateTime;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache; // Import Cache facade
use Illuminate\Support\Facades\Date;

class register
{
    /**
     * Register a new user and create related records based on user type.
     *
     * @param array $data
     * @return User
     */
    public function register(array $data): User
    {
        // Validate that either email or phone is provided (but not both)
        if (!isset($data['email']) && !isset($data['phone'])) {
            throw new \Exception('يجب أن تحتوي البيانات إما على البريد الإلكتروني أو رقم الهاتف.');
        }

        // Create user data array
        $userData = [
            'name' => $data['name'],
            'password' => Hash::make($data['password']),
        ];

        // Set email or phone based on input
        if (isset($data['email'])) {
            $userData['email'] = $data['email'];
        } else {
            $userData['phone'] = $data['phone'];
        }

        // Create the user
        $user = User::create($userData);

        // Create and attach access token
        $user->token = $user->createToken($user->name . '-AuthToken')->plainTextToken;

        return $user;
    }


    public function verifyOtp(string $otp, User $user): bool
    {
        // Retrieve the OTP data from the cache using the authenticated user's ID
        $otpData = Cache::get('otp_' . $user->id);

        // Check if the OTP data exists in the cache
        if (!$otpData) {
            throw new \Exception('No OTP data found in cache.');
        }

        // Retrieve the OTP from the cache data
        $sessionOtp = $otpData['otp'];

        // Check if the OTP matches
        if ($otp !== $sessionOtp) {
            throw new \Exception('Invalid OTP.');
        }

        // If OTP is valid, update the user's otp_verified column
        $user->otp = 1; // Assuming the column name is otp_verified
        $user->email_verified_at = Date::now();
        $user->save(); // Save the changes to the database

        // Clear the OTP data from the cache after successful verification
        Cache::forget('otp_' . $user->id);

        return true; // Return true if OTP verification is successful
    }

    public function generateRandomPassword($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomPassword = '';
        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomPassword;
    }
}
