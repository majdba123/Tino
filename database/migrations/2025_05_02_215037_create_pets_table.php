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
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // نوع الحيوان (كلب، قطة، إلخ)
            $table->string('name_cheap'); // نوع الحيوان (كلب، قطة، إلخ)
            $table->string('breed'); // نوع الحيوان (كلب، قطة، إلخ)
            $table->date('birth_date')->nullable(); // تاريخ الميلاد بدلاً من العمر
            $table->enum('gender', ['male', 'female']); // الجنس
            $table->string('image')->nullable();

            $table->enum('health_status', ['excellent', 'good', 'fair', 'poor']); // الحالة الصحية
            $table->enum('status', ['active', 'inactive', 'deceased'])->default('active'); // الحالة
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
