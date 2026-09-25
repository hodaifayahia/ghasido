<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every file the platform stores, wherever it lives (MED-01, MED-02, MED-07,
 * DATA-02, spec 0003 B.3).
 *
 * `disk` says which side of the privacy line the file sits on: `public` for
 * lesson media that may be cached and linked, `local` for learner recordings
 * and anything else served only through MediaController behind
 * MediaAssetPolicy (PRIV-04, SEC-04).
 *
 * `kind` and `library` are plain strings cast to enums, never
 * $table->enum(): the same file runs on SQLite in CI and MySQL 8.4 locally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('disk');
            $table->string('path');
            // The upload's own filename is metadata only; the stored name is
            // always a UUID (AGENTS.md §6, Media and storage).
            $table->string('original_name')->nullable();
            $table->string('mime');
            $table->string('kind');
            // Required on every image upload (MED-07, ACC-05); nullable here
            // because audio and recordings have nothing to describe.
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            // Derivatives by name, e.g. {"thumb": "content/.../x-320.webp"}.
            $table->json('variants')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            // Null means the asset belongs to the shared catalogue.
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('library');
            $table->string('category')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['library', 'kind']);
            $table->index('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
