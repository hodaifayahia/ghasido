<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_catalog_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('individual_price_dzd')->default(0);
            $table->decimal('individual_price_usd', 10, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('subscription_catalog_settings')->insert([
            'id' => 1,
            'individual_price_dzd' => 0,
            'individual_price_usd' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_catalog_settings');
    }
};
