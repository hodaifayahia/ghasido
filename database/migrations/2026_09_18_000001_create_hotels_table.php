<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant table (spec 0002, ORG-01, SUB-04).
 *
 * `access_state` is a plain string cast to App\Enums\HotelAccessState, never
 * $table->enum(): the same migration runs on SQLite in CI and MySQL 8.4
 * locally, and an enum column is a schema change on one and a check constraint
 * on the other.
 *
 * Nothing time based is stored. Expiring and ended are derived from
 * `contract_ends_on` at read time, so no column here can disagree with the
 * calendar (spec 0002, invariant 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city');

            // Contact details, not a login. Manager accounts are deferred, so
            // this is deliberately not a foreign key and the email is not
            // unique: one person may run two hotels.
            $table->string('manager_name');
            $table->string('manager_email');

            $table->string('access_state')->index();

            $table->date('contract_starts_on')->nullable();
            $table->date('contract_ends_on')->nullable()->index();
            $table->timestamp('paused_at')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Archive, never delete: access stops and every row stays
            // (DATA-10). No SoftDeletes, because an explicit timestamp does
            // not silently filter later queries.
            $table->timestamp('archived_at')->nullable();
            $table->text('archive_reason')->nullable();

            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
