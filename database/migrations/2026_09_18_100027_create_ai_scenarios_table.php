<?php

use App\Enums\ContentStatus;
use App\Enums\ScenarioDifficulty;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An AI role-play scenario (RP-01, RP-02, RP-04, RP-05, RP-06, spec 0003
 * B.5).
 *
 * Everything the role-play prompt is assembled from lives here: situation,
 * the two roles, the objective, the turn bounds. The prompt is never accepted
 * raw from the client (RP-04). `attempts_allowed` defaults to 3 and one
 * attempt is one complete conversation (RP-05, RP-06). `feedback_criteria`
 * is the list of {key, label, weight} the evaluation scores against, stored
 * so the admin can change it without a code change (AIE-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            // The card subtitle on the scenario picker (photo_15).
            $table->string('description');
            $table->string('difficulty')->default(ScenarioDifficulty::Beginner->value);
            $table->text('situation');
            $table->text('ai_role');
            $table->text('employee_role');
            $table->text('objective');
            $table->json('goals')->nullable();
            $table->json('useful_phrases')->nullable();
            $table->json('feedback_criteria')->nullable();
            $table->unsignedSmallInteger('attempts_allowed')->default(3);
            $table->string('input_mode')->default('both');
            $table->unsignedSmallInteger('min_turns')->default(4);
            $table->unsignedSmallInteger('max_turns')->default(12);
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            // bell|bed|alert|calendar|info|lotus: the card glyph.
            $table->string('icon')->default('bell');
            $table->string('quote')->nullable();
            $table->text('tip')->nullable();
            $table->string('status')->default(ContentStatus::Draft->value);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['department_id', 'hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_scenarios');
    }
};
