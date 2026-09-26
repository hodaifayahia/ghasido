<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * When the user saw the one-time welcome animation after their very first
 * sign-in (client request 2026-09-26). Server-side, so it plays once per
 * account, not once per browser. Everyone who has already signed in is
 * treated as welcomed, so only new accounts see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('welcomed_at')->nullable();
        });

        DB::table('users')
            ->whereNull('welcomed_at')
            ->where(fn ($query) => $query->whereNotNull('last_login_at')->orWhereNotNull('first_login_completed_at'))
            ->update(['welcomed_at' => DB::raw('COALESCE(last_login_at, first_login_completed_at)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('welcomed_at');
        });
    }
};
