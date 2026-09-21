<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
{
    if (Schema::hasTable('appointments')) {
        return;
    }

    Schema::create('appointments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('animal_type');
        $table->date('date');
        $table->time('time');
        $table->string('status')->default('pending');
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};