<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('apartment', 50)->nullable();
            $table->string('suite', 50)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('emergency_contact_name', 255)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_email', 255)->nullable();
            $table->enum('communication_preference', ['email', 'notification', 'both'])->nullable();
            $table->string('contact_phone', 20)->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth',
                'gender',
                'country',
                'city',
                'street',
                'address',
                'apartment',
                'suite',
                'postal_code',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_email',
                'communication_preference',
                'contact_phone'
            ]);
        });
    }
};
