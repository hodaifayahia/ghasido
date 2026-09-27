<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Buying online with a manual payment (client request 2026-09-27):
 *
 * - Plans now serve two audiences. Hotel plans are sized by employee seats;
 *   individual plans give one person a monthly AI point allowance, with no
 *   seats. Three individual plans are seeded; the Super Admin prices them.
 * - An individual who buys online waits for approval like a hotel does.
 * - Every checkout leaves a payment submission: the chosen method, the
 *   amount, the transaction reference the customer typed and the receipt
 *   they uploaded (kept private). It follows the account: confirmed when the
 *   hotel or individual is approved, rejected when they are rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->string('audience', 20)->default('hotel')->index();
        });

        $now = now();
        $individualPlans = [
            ['Starter', 'individual-starter', 1500, 9, 2000],
            ['Plus', 'individual-plus', 3000, 18, 5000],
            ['Pro', 'individual-pro', 6000, 35, 12000],
        ];

        foreach ($individualPlans as [$name, $slug, $dzd, $usd, $points]) {
            if (DB::table('subscription_plans')->where('slug', $slug)->exists()) {
                continue;
            }

            // Plan names are unique across both audiences.
            $label = DB::table('subscription_plans')->where('name', $name)->exists() ? "Individual {$name}" : $name;

            DB::table('subscription_plans')->insert([
                'name' => $label,
                'slug' => $slug,
                'audience' => 'individual',
                'employee_limit' => 1,
                'price_dzd' => $dzd,
                'price_usd' => $usd,
                'points_per_employee' => $points,
                'bonus_points_per_employee' => 0,
                'voice_points_per_10_minutes' => 100,
                'ai_action_points' => 50,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('individual_subscriptions', function (Blueprint $table): void {
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            // approved (every admin-created subscription), pending (bought
            // online, waiting for the Super Admin), rejected.
            $table->string('approval_state', 20)->default('approved')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
        });

        Schema::create('payment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            // Exactly one of the two is set: who the payment is for.
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('individual_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name', 120);
            $table->string('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_method_id')->nullable()->constrained('subscription_payment_methods')->nullOnDelete();
            $table->string('payment_method_name', 80);
            $table->string('reference', 120)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_name')->nullable();
            $table->string('proof_mime', 100)->nullable();
            $table->unsignedInteger('proof_size')->nullable();
            $table->string('payer_name', 120);
            $table->string('payer_email', 255);
            $table->string('payer_phone', 40)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_submissions');

        Schema::table('individual_subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('subscription_plan_id');
            $table->dropColumn(['approval_state', 'approved_at', 'rejection_reason']);
        });

        DB::table('subscription_plans')->where('audience', 'individual')->delete();

        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn('audience');
        });
    }
};
