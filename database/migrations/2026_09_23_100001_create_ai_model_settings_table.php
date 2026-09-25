<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Super Admin's AI model overrides (API-04): one row whose `values` json
 * holds model ids and a fake/real switch per capability, each blank meaning
 * "use .env". `checks` holds the last connection test per capability
 * (PERF-04). Keys and base URLs are never stored here (API-02, SEC-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('values')->nullable();
            $table->json('checks')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_settings');
    }
};
