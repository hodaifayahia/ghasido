<?php

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A course: an ordered group of units and lessons for one department
 * (ORG-04, CMS-04, spec 0003 B.5).
 *
 * `hotel_id` null means shared across every hotel; set means one hotel's own
 * course. Both are visible to that hotel's learners
 * (Course::scopeForLearner). No ScopedToHotel here for that reason: a tenant
 * scope would hide the shared catalogue.
 *
 * `tone` is a Guesvia colour token name (brand|aqua|success|warning|gold|
 * danger) driving the tree icon in the admin, never a hex value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('tone')->default('brand');
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('status')->default(ContentStatus::Draft->value);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The learner query: department, then shared-or-mine, then status.
            $table->index(['department_id', 'hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
