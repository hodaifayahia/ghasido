<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the authoring controls that are shared by AI scenarios (RP-04,
 * AIE-04, GEN-03). Keeping them server-side makes the authoring tabs real
 * configuration instead of mockup-only copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_scenario_configurations', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_scenario_configurations');
    }
};
