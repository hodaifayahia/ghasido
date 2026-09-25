<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic reminder rules (REM-03, spec 0003 B.7).
 *
 * `trigger` is a string cast to AutomationTrigger. `days` is the inactivity
 * window for `inactive_days` and the repeat window for every trigger (null
 * means the runner's default of 7). `audience` is `{hotel_ids: [], department_ids: []}`;
 * null or empty lists mean everyone (REM-07 scoping happens on the rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger');
            $table->unsignedSmallInteger('days')->nullable();
            $table->foreignId('template_id')->constrained('reminder_templates')->cascadeOnDelete();
            $table->json('audience')->nullable();
            $table->string('audience_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'trigger']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
