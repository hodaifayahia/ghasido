<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An individual subscriber may study one department or several (owner
 * request 2026-09-25). The first one chosen stays on users.department_id
 * (their main department, what reports show); with more than one they switch
 * between them like a manager does (ResolveTrainingDepartment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_subscription_department', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('individual_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['individual_subscription_id', 'department_id'], 'individual_subscription_department_unique');
        });

        // Subscribers added before this table study their one department.
        $now = now();
        $rows = DB::table('individual_subscriptions')
            ->join('users', 'users.id', '=', 'individual_subscriptions.user_id')
            ->whereNotNull('users.department_id')
            ->get(['individual_subscriptions.id as subscription_id', 'users.department_id']);

        foreach ($rows as $row) {
            DB::table('individual_subscription_department')->insert([
                'individual_subscription_id' => $row->subscription_id,
                'department_id' => $row->department_id,
                'position' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_subscription_department');
    }
};
