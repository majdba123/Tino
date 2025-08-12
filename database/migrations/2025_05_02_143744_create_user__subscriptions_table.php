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
        Schema::create('user__subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->integer('remaining_calls');
            $table->integer('remaining_visits');
            $table->decimal('price_paid', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->string('stop_at')->nullable(); // نوع الحيوان (كلب، قطة، إلخ)
             $table->string('payment_method')->nullable(); // نوع الحيوان (كلب، قطة، إلخ)
            $table->string('payment_status')->nullable(); // نوع الحيوان (كلب، قطة، إلخ)
            $table->string('payment_session_id')->nullable(); // نوع الحيوان (كلب، قطة، إلخ)




            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user__subscriptions');
    }
};
