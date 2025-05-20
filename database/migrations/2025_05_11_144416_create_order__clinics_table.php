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
        Schema::create('order__clinics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->onDelete('cascade');
            $table->foreignId('clinic_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->text('clinic_note')->nullable();
            $table->decimal('price_order', 10, 2)->nullable();         // Original price
            $table->boolean('have_discount')->default(false);           // Whether there's a discount
            $table->decimal('discount_percent', 5, 2)->nullable();     // Discount percentage (0-100)
            $table->decimal('discount_amount', 10, 2)->nullable();      // Calculated discount amount
            $table->decimal('final_price', 10, 2)->nullable();        // Final price after discount
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order__clinics');
    }
};
