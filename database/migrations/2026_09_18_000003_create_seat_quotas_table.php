<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many employee seats a hotel bought in a department (SUB-01, spec 0002).
 *
 * This table is also what gives a hotel its departments: a hotel "has" a
 * department when it holds a quota row for it, which is why one hotel shows 7
 * departments and another 4 (spec 0002, AC-8).
 *
 * There is deliberately no used_seats column. Usage is counted live from
 * active employee accounts, because a cached count drifts the moment one write
 * path forgets to update it, and these numbers are what the client bills
 * against (spec 0002, invariant 1; AGENTS.md refuses the field for SUB-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('allowed_seats')->default(0);
            $table->timestamps();

            $table->unique(['hotel_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_quotas');
    }
};
