<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An employee's uploaded voice recording (TEST-07, DATA-02, RESP-05,
 * spec 0003 B.6).
 *
 * The file itself is a `media_assets` row on the private disk, served only
 * through an authorizing controller (PRIV-04, SEC-04). This row ties it to
 * what it was recorded for (a block today, an attempt or role-play turn
 * later) through the `recordable` morph. Write once: `created_at` only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('recordable');
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('transcript')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_recordings');
    }
};
