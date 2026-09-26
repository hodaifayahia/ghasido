<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The Arabic copy of the public landing page (I18N-02, user request
 * 2026-09-26), edited next to the English one in Website Management. Null
 * until first edited: the built-in Arabic defaults show until then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_page_contents', function (Blueprint $table): void {
            $table->json('content_ar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('landing_page_contents', function (Blueprint $table): void {
            $table->dropColumn('content_ar');
        });
    }
};
