<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelter_donation_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shelter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donation_request_id')->nullable()->constrained('shelter_donation_requests')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('donor_name');
            $table->string('phone')->nullable();
            $table->string('donation_type')->nullable(); // نقدي / عيني
            $table->string('amount_or_item')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('pending'); // pending / approved / rejected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelter_donation_submissions');
    }
};