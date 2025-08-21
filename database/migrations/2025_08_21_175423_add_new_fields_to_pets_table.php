<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::table('pets', function (Blueprint $table) {
        $table->string('color', 50)->nullable();
        $table->decimal('weight', 5, 2)->nullable()->comment('Weight in kg');
        $table->boolean('is_spayed')->default(false);
        $table->json('allergies')->nullable();
        $table->json('previous_surgeries')->nullable();
        $table->json('chronic_conditions')->nullable();
        $table->json('vaccination_history')->nullable();
        $table->date('last_veterinary_visit')->nullable();
        $table->string('current_veterinary', 255)->nullable();
        $table->string('insurance_company', 255)->nullable();
        $table->string('policy_number', 100)->nullable();
        $table->text('coverage_details')->nullable();
    });
}

public function down()
{
    Schema::table('pets', function (Blueprint $table) {
        $table->dropColumn([
            'color',
            'weight',
            'is_spayed',
            'allergies',
            'previous_surgeries',
            'chronic_conditions',
            'vaccination_history',
            'last_veterinary_visit',
            'current_veterinary',
            'insurance_company',
            'policy_number',
            'coverage_details'
        ]);
    });
}
};
