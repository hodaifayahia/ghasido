<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departments are data, not an enum (ORG-02, spec 0002).
 *
 * `hotel_id` null means the shared catalogue every hotel draws from
 * (Reception, Spa, Housekeeping and so on). `hotel_id` set means a department
 * only one hotel has (ORG-04).
 *
 * The composite unique cannot constrain the global rows, because both MySQL
 * and SQLite allow repeated nulls in a unique index. Uniqueness among global
 * departments is a validation rule instead, in App\Concerns\DepartmentRules.
 * That is a known limitation, recorded in the spec, not an oversight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'slug']);
            $table->index('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
