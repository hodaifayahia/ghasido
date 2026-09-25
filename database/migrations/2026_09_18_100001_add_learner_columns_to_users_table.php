<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a learner account carries beyond name and password (spec 0003 B.1).
 *
 * Consent and acknowledgement are timestamps, not booleans, so a grant and a
 * later revocation are both dated (PRIV-01, PRIV-02, REM-05). Nothing here is
 * derived: every column is written by an explicit action (first login, a step
 * completion, an admin creating the account).
 *
 * No ->after(): silently ignored on SQLite, so column order must never carry
 * meaning (AGENTS.md §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The login identifier (AUTH-01). Nullable so the admin accounts
            // that already exist, and the starter's test user, keep working
            // with an email only.
            $table->string('username', 40)->nullable()->unique();

            $table->timestamp('email_consent_at')->nullable();
            $table->timestamp('first_login_completed_at')->nullable();
            $table->timestamp('research_notice_acknowledged_at')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('training_started_at')->nullable();
            $table->timestamp('training_completed_at')->nullable();

            // The anonymous identifier the research export uses instead of a
            // name (REP-07): "P-" plus six uppercase alphanumerics.
            $table->string('participant_code', 12)->nullable()->unique();
            $table->string('cohort')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['participant_code']);
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'email_consent_at',
                'first_login_completed_at',
                'research_notice_acknowledged_at',
                'last_login_at',
                'last_activity_at',
                'training_started_at',
                'training_completed_at',
                'participant_code',
                'cohort',
            ]);
        });
    }
};
