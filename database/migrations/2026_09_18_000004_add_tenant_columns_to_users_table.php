<?php

use App\Enums\AccountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant scoping spec 0001 was waiting for (ROLE-02, ORG-03, spec 0002).
 *
 * Until these columns exist there is no way to say which hotel a user belongs
 * to, which is why spec 0001 recorded an ordering constraint: no admin screen
 * serves real hotel data before this lands.
 *
 * Both foreign keys are nullable. A Super Admin belongs to no hotel, and every
 * account that already exists takes null, so nothing may assume they are set.
 *
 * No ->after(): it is silently ignored on SQLite, so relying on it for column
 * order would work locally on MySQL and mean nothing in CI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            // Seat usage counts active accounts only, so deactivating somebody
            // frees their seat without deleting a single row (DATA-10).
            $table->string('status')->default(AccountStatus::Active->value);

            // The composite the seat count leans on: hotel, department, status.
            $table->index(['hotel_id', 'department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['hotel_id', 'department_id', 'status']);
            $table->dropConstrainedForeignId('hotel_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('status');
        });
    }
};
