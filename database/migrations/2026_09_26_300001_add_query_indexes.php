<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries that read the tables which grow without limit
 * (DATA-10: attempts, completions and reminders are never deleted).
 *
 * Only these five tables get one. MySQL already indexes every foreign key,
 * the content and settings tables stay small, `users` is read through its
 * (hotel_id, department_id, status) index, `ai_usages` is covered, and
 * `audit_logs` is only ever written.
 *
 * - reminders (user_id, status, sent_at) replaces (user_id, status): the
 *   topbar bell reads the last 24 hours of a user's sent reminders on every
 *   page (REM-08), and the old index stopped before `sent_at`.
 * - roleplay_attempts (is_preview, started_at): the dashboard activity feed
 *   and the Super Admin's monthly voice-call points (AIL-01), which both
 *   scanned the whole table.
 * - attempts (user_id, submitted_at) replaces (user_id, activity_id), which
 *   no query used: the Reports answers tab and export read a population's
 *   answers by date range (REP-06), the coach a learner's last 30 days.
 * - attempts (placement_id, user_id): a learner's practice attempts on a
 *   block (PRAC-07), read on every practice step. On MySQL it also takes
 *   over from the index behind the placement_id foreign key.
 * - lesson_completions (completed_at): the dashboard feed's latest
 *   completions, which sorted the whole table.
 * - test_attempts (status, submitted_at) replaces (status): the dashboard
 *   feed's latest submitted sittings.
 *
 * On MySQL this adds two indexes in all; the rest replace one. A new index
 * is added before the one it replaces is dropped, because MySQL refuses to
 * drop the last index behind a foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'sent_at']);
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('roleplay_attempts', function (Blueprint $table): void {
            $table->index(['is_preview', 'started_at']);
        });

        Schema::table('attempts', function (Blueprint $table): void {
            $table->index(['user_id', 'submitted_at']);
            $table->index(['placement_id', 'user_id']);
            $table->dropIndex(['user_id', 'activity_id']);
        });

        // The composite now backs the placement_id foreign key. MySQL drops
        // its own single-column index for it; drop any still left (MariaDB,
        // or the one down() puts back) so the column is not indexed twice.
        $leftover = $this->placementOnlyIndexes();

        if ($leftover !== []) {
            Schema::table('attempts', function (Blueprint $table) use ($leftover): void {
                foreach ($leftover as $name) {
                    $table->dropIndex($name);
                }
            });
        }

        Schema::table('lesson_completions', function (Blueprint $table): void {
            $table->index('completed_at');
        });

        Schema::table('test_attempts', function (Blueprint $table): void {
            $table->index(['status', 'submitted_at']);
            $table->dropIndex(['status']);
        });
    }

    public function down(): void
    {
        Schema::table('test_attempts', function (Blueprint $table): void {
            $table->index('status');
            $table->dropIndex(['status', 'submitted_at']);
        });

        Schema::table('lesson_completions', function (Blueprint $table): void {
            $table->dropIndex(['completed_at']);
        });

        // MySQL and MariaDB need an index behind the placement_id foreign key
        // before the composite that backs it can go.
        $needsForeignKeyIndex = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

        Schema::table('attempts', function (Blueprint $table) use ($needsForeignKeyIndex): void {
            if ($needsForeignKeyIndex) {
                $table->index('placement_id');
            }

            $table->index(['user_id', 'activity_id']);
            $table->dropIndex(['placement_id', 'user_id']);
            $table->dropIndex(['user_id', 'submitted_at']);
        });

        Schema::table('roleplay_attempts', function (Blueprint $table): void {
            $table->dropIndex(['is_preview', 'started_at']);
        });

        Schema::table('reminders', function (Blueprint $table): void {
            $table->index(['user_id', 'status']);
            $table->dropIndex(['user_id', 'status', 'sent_at']);
        });
    }

    /**
     * @return list<string>
     */
    private function placementOnlyIndexes(): array
    {
        $names = [];

        foreach (Schema::getIndexes('attempts') as $index) {
            if ($index['columns'] === ['placement_id']) {
                $names[] = $index['name'];
            }
        }

        return $names;
    }
};
