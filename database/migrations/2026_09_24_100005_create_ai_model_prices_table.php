<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each AI model costs, as the Super Admin enters it (API-03, AIL-04;
 * spec 0005 §4.3). Prices change and differ by account, so they are data,
 * never code: one row per model id as the provider reports it, per million
 * units (tokens for text models, characters for speech). `ai_usages`
 * rows are priced from here when they are written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('model', 150)->unique();
            $table->string('unit', 20)->default('tokens');
            $table->decimal('input_per_million', 12, 4)->default(0);
            $table->decimal('output_per_million', 12, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_prices');
    }
};
