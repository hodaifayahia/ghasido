<?php

use App\Enums\DepartmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two columns the Departments mockup shows per row (spec 0003, B.2).
 *
 * `focus` is the one-line description under the name. `status` is the
 * editorial pill (active / review / draft), stored as a string and cast to
 * DepartmentStatus, never a database enum, so it runs on SQLite and MySQL
 * alike. No ->after(): SQLite ignores it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('focus')->nullable();
            $table->string('status')->default(DepartmentStatus::Active->value);
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['focus', 'status']);
        });
    }
};
