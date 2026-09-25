<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('recipient_name', 120)->nullable();
            $table->string('account_reference', 180)->nullable();
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        $now = now();

        DB::table('subscription_payment_methods')->insert([
            [
                'name' => 'BaridiMob',
                'recipient_name' => null,
                'account_reference' => null,
                'instructions' => null,
                'sort_order' => 1,
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'RedotPay',
                'recipient_name' => null,
                'account_reference' => null,
                'instructions' => null,
                'sort_order' => 2,
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_methods');
    }
};
