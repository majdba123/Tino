<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('name'); // User's name
            $table->string('google_id')->nullable(); // Google ID for Social Login
            $table->string('facebook_id')->nullable(); // Facebook ID for Social Login
            $table->string('phone')->nullable(); // Phone number
            $table->string('email')->unique(); // Email address
            $table->string('otp')->default(0); // OTP for phone verification
            $table->string('type')->default(0); // OTP for phone verification
            $table->string('image')->nullable();
            $table->string('password'); // Password (hashed)
            $table->timestamp('email_verified_at')->nullable(); // Email verification
            $table->string('status')->default('active'); // OTP for phone verification

            $table->rememberToken(); // For "remember me" functionality
            $table->timestamps(); // Created at and updated at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
