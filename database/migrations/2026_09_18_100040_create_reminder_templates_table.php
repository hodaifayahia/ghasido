<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reusable messages an admin sends or schedules (REM-04, spec 0003 B.7).
 *
 * `body` may hold the six variables TemplateRenderer knows:
 * {{name}} {{hotel}} {{department}} {{progress}} {{days_remaining}} {{login_url}}.
 * `audience_label` and `trigger_label` are display copy for the templates
 * card on the Messages screen; they carry no logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->string('audience_label')->nullable();
            $table->string('trigger_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_templates');
    }
};
