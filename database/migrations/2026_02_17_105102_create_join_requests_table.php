<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('join_requests', function (Blueprint $table) {
            $table->id();

            // المستخدم الذي قدم الطلب
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // الدور المطلوب
            $table->string('requested_role'); 
            // doctor | shop_owner | shelter_owner

            // حالة الطلب
            $table->string('status')->default('pending'); 
            // pending | approved | rejected

            // لاحقاً لو بدنا وثائق
            $table->string('document_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('join_requests');
    }
};
