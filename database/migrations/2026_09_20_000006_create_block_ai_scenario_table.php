<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Connect an AI role-play block (and therefore its lesson step) to one or
 * more reusable scenarios (RP-14, TSTM-05, BLD-02).
 *
 * The JSON setting existed before this relationship. Existing role-play
 * blocks are copied into the pivot so no lesson loses its scenarios during
 * the migration; the setting remains as a read fallback for old imports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_ai_scenario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_scenario_id')->constrained('ai_scenarios')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();

            $table->unique(['block_id', 'ai_scenario_id']);
            $table->index(['ai_scenario_id', 'position']);
        });

        foreach (DB::table('blocks')->where('type', 'ai_roleplay')->get(['id', 'settings']) as $block) {
            $settings = is_string($block->settings) ? json_decode($block->settings, true) : $block->settings;
            $ids = is_array($settings) && is_array($settings['scenario_ids'] ?? null)
                ? array_values(array_unique(array_map('intval', array_filter($settings['scenario_ids'], 'is_numeric'))))
                : [];
            $existingIds = $ids === []
                ? []
                : DB::table('ai_scenarios')->whereIn('id', $ids)->pluck('id')->map('intval')->all();

            foreach ($existingIds as $index => $scenarioId) {
                DB::table('block_ai_scenario')->insertOrIgnore([
                    'block_id' => $block->id,
                    'ai_scenario_id' => $scenarioId,
                    'position' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('block_ai_scenario');
    }
};
