<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_ai_point_top_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->date('month_start');
            $table->unsignedInteger('points');
            $table->unsignedBigInteger('amount_dzd');
            $table->foreignId('payment_method_id')->nullable()->constrained('subscription_payment_methods')->nullOnDelete();
            $table->string('payment_reference', 120)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['hotel_id', 'month_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_ai_point_top_ups');
    }
};
