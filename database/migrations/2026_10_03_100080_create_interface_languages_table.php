<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interface languages beyond English and Arabic, added from Settings and
 * translated by AI or by hand (client request 2026-10-03: "the language at
 * the top — if I want to add other languages later"). The strings live in
 * the database, not in lang/, so a deploy never overwrites them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interface_languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 60);
            $table->string('native_name', 60);
            $table->string('direction', 3)->default('ltr');
            $table->boolean('enabled')->default(false);
            $table->longText('messages')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('failed_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_languages');
    }
};
