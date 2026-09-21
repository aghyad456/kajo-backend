<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('clinic_slots')) return;

        Schema::create('clinic_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();

            $table->date('date');
            $table->time('time');

            $table->string('status')->default('available'); // available | booked | cancelled
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['clinic_id', 'date', 'time']);
            $table->index(['clinic_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_slots');
    }
};