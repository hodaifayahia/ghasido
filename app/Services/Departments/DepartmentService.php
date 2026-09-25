<?php

namespace App\Services\Departments;

use App\Enums\DepartmentStatus;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every write to a department (spec 0003 Part D; spec 0002, AC-20 shape).
 *
 * The controller validates through a form request and hands the clean data
 * here. Each method changes the row and writes exactly one audit row inside
 * one transaction, so a change cannot reach the database without its trace
 * (SEC-06, ADM-03).
 *
 * Nothing here deletes: archiving flips `is_active` and keeps every employee,
 * quota, course and attempt that points at the row (DATA-10).
 */
class DepartmentService
{
    /**
     * @param  array{name: string, slug: string, focus: ?string, status: DepartmentStatus, hotel_id: ?int}  $data
     */
    public function create(array $data): Department
    {
        return DB::transaction(function () use ($data): Department {
            $department = new Department;
            $department->fill([
                'name' => $data['name'],
                'slug' => $data['slug'] !== '' ? $data['slug'] : $this->fallbackSlug($data['name']),
                'focus' => $data['focus'],
                'status' => $data['status'],
                'hotel_id' => $data['hotel_id'],
                'position' => $this->nextPosition($data['hotel_id']),
                'is_active' => true,
            ]);
            $department->save();

            AuditLog::record($department, 'department.created', [
                'created' => $department->only(['name', 'slug', 'focus', 'status', 'hotel_id']),
            ]);

            return $department;
        });
    }

    /**
     * Edit the typed fields. The scope (shared or one hotel) is fixed at
     * creation: moving a department between catalogues would silently change
     * who may see the content and employees attached to it (ROLE-02).
     *
     * @param  array{name: string, slug: string, focus: ?string, status: DepartmentStatus}  $data
     */
    public function update(Department $department, array $data): Department
    {
        return $this->change($department, 'department.updated', function (Department $department) use ($data): void {
            $department->fill([
                'name' => $data['name'],
                'slug' => $data['slug'] !== '' ? $data['slug'] : $department->slug,
                'focus' => $data['focus'],
                'status' => $data['status'],
            ]);
        });
    }

    /**
     * Archive or restore. Archived means switched off (`is_active` false):
     * it drops out of the default directory and the seat catalogue, and
     * nothing that references it is touched (DATA-10, AUTH-08).
     */
    public function toggle(Department $department): Department
    {
        $action = $department->is_active ? 'department.archived' : 'department.restored';

        return $this->change($department, $action, function (Department $department): void {
            $department->is_active = ! $department->is_active;
        });
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Apply one change and its audit row in one transaction. The audit row is
     * written while the attributes are still dirty, so the before and after
     * values come from Eloquent, never rebuilt by hand.
     *
     * @param  callable(Department): void  $mutate
     */
    private function change(Department $department, string $action, callable $mutate): Department
    {
        return DB::transaction(function () use ($department, $action, $mutate): Department {
            $mutate($department);
            AuditLog::record($department, $action);
            $department->save();

            return $department;
        });
    }

    /**
     * New rows go to the end of their catalogue (the shared one, or the
     * hotel's own), so the directory order stays the order people know.
     */
    private function nextPosition(?int $hotelId): int
    {
        $max = Department::query()
            ->when($hotelId === null, fn ($query) => $query->whereNull('hotel_id'))
            ->when($hotelId !== null, fn ($query) => $query->where('hotel_id', $hotelId))
            ->max('position');

        return $max === null ? 0 : ((int) $max) + 1;
    }

    /**
     * A name made only of characters Str::slug drops (say, Arabic) still
     * needs a slug. The validation already proved a non empty slug unique,
     * so this only runs for the empty case, and counts up until free.
     */
    private function fallbackSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'department';
        $slug = $base;
        $counter = 2;

        while (Department::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
