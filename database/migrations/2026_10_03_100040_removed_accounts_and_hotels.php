<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Delete" for an employee or a hotel (client request 2026-10-02: archive
 * and deactivate keep everything on screen; deleting should free the email
 * and username for another account). A deleted account loses its personal
 * details and leaves every list, but its answers stay as anonymous records
 * for the reports (DATA-10, PRIV-03); a deleted hotel leaves every list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('removed_at')->nullable()->index();
        });

        Schema::table('hotels', function (Blueprint $table): void {
            $table->timestamp('removed_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['removed_at']);
            $table->dropColumn('removed_at');
        });

        Schema::table('hotels', function (Blueprint $table): void {
            $table->dropIndex(['removed_at']);
            $table->dropColumn('removed_at');
        });
    }
};
