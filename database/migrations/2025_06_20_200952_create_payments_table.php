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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // تغيير طريقة تعريف المفتاح الخارجي
            $table->unsignedBigInteger('user_subscription_id');
            $table->foreign('user_subscription_id')
                  ->references('id')
                  ->on('user__subscriptions')
                  ->onDelete('cascade');
                        $table->string('payment_id')->unique(); // معرف الدفع من Stripe
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('payment_method')->default('stripe');
            $table->string('status'); // pending, paid, failed, refunded
            $table->json('details')->nullable(); // تفاصيل إضافية من Stripe
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
