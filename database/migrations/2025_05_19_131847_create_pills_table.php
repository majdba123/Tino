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
        Schema::create('pills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_clinic_id')->constrained('order__clinics')->onDelete('cascade');
            $table->text('medical_details');
            $table->text('clinic_note');
            $table->decimal('price_order', 10, 2);
            $table->boolean('have_discount')->default(false);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_price', 10, 2);
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pills');
    }
};
