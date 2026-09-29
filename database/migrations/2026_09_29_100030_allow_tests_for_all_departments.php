<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Pre-test or Post-test may apply to every department (client request
 * 2026-09-29: "the Pre-test comes before any department, for every
 * employee"). A null department means "all departments": a learner sits
 * their own department's test when there is one, otherwise this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table): void {
            $table->unsignedBigInteger('department_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Irreversible without choosing a department for "all departments"
        // tests; leaving the column nullable loses nothing.
    }
};
