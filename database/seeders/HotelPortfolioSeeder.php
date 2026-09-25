<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\Role as RoleEnum;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * A realistic portfolio to build the Hotels page against (spec 0002, AC-19).
 *
 * It reproduces the six hotels the page shows today, with their real names,
 * managers, cities, department counts and seat numbers, plus one pending and
 * one archived hotel so the states the sample data never had are exercised.
 *
 * Two things worth knowing before you compare it against the old page:
 *
 * 1. It creates the employee accounts behind every seat figure. Usage is
 *    counted live from active employee accounts, so without real users every
 *    hotel would seed at 0 seats used and the page would look broken.
 *
 * 2. It reproduces the sample data's SHAPE, not its contradictions. The old
 *    page called 18 and 29 days remaining "Active" while calling 11 days
 *    "Expiring Soon", which cannot all be true under one rule. Spec 0002
 *    settled the rule (a 30 day window from config), so the two hotels the
 *    page labelled Active are seeded comfortably outside that window. The
 *    result is the same distribution the stat cards show, 2 active, 2
 *    expiring, 1 paused and 1 ended, under a rule that actually holds.
 *
 * Idempotent: keyed on slug, so running it twice leaves one portfolio.
 */
class HotelPortfolioSeeder extends Seeder
{
    /**
     * The shared catalogue (ORG-02). Order matches the dashboard mockup.
     *
     * @var list<string>
     */
    private const CATALOGUE = [
        'Reception',
        'Spa',
        'Housekeeping',
        'Food Service',
        'Kitchen',
        'Marketing',
        'Technical Services',
    ];

    /**
     * The portfolio. `quotas` maps a department name to [used, allowed], and
     * the used figure is how many active employee accounts get created.
     *
     * @return list<array<string, mixed>>
     */
    private function portfolio(): array
    {
        return [
            [
                'name' => "La Gazelle d'Or",
                'manager' => 'Meriem Haddad',
                'email' => 'meriem.haddad@guesvia.dz',
                'city' => 'Algiers',
                'state' => 'active',
                'days' => 45,
                // 58 used of 64 allowed, across 7 departments, exactly as the
                // hotel sidebar lists them.
                'quotas' => [
                    'Reception' => [5, 5],
                    'Spa' => [7, 8],
                    'Housekeeping' => [10, 12],
                    'Food Service' => [12, 12],
                    'Kitchen' => [9, 10],
                    'Marketing' => [7, 6],
                    'Technical Services' => [8, 11],
                ],
            ],
            [
                'name' => 'Hotel El Aurassi',
                'manager' => 'Yacine Merabet',
                'email' => 'yacine.merabet@guesvia.dz',
                'city' => 'Algiers',
                'state' => 'active',
                'days' => 60,
                // 50 of 48: over quota, which is only reachable by lowering a
                // quota below live usage (SUB-03).
                'quotas' => [
                    'Reception' => [9, 8],
                    'Spa' => [6, 6],
                    'Housekeeping' => [11, 10],
                    'Food Service' => [10, 10],
                    'Kitchen' => [8, 8],
                    'Marketing' => [6, 6],
                ],
            ],
            [
                'name' => 'Sheraton Club des Pins',
                'manager' => 'Nabila Rahmani',
                'email' => 'nabila.rahmani@guesvia.dz',
                'city' => 'Staoueli',
                'state' => 'expiring',
                'days' => 11,
                // 36 of 36: at capacity.
                'quotas' => [
                    'Reception' => [6, 6],
                    'Spa' => [5, 5],
                    'Housekeeping' => [8, 8],
                    'Food Service' => [7, 7],
                    'Kitchen' => [6, 6],
                    'Marketing' => [4, 4],
                ],
            ],
            [
                'name' => 'Azure Resort & Spa',
                'manager' => 'Sofiane Bensaid',
                'email' => 'sofiane.bensaid@guesvia.dz',
                'city' => 'Oran',
                'state' => 'expiring',
                'days' => 6,
                // 18 of 20.
                'quotas' => [
                    'Reception' => [4, 4],
                    'Spa' => [3, 4],
                    'Housekeeping' => [5, 5],
                    'Food Service' => [4, 4],
                    'Kitchen' => [2, 3],
                ],
            ],
            [
                'name' => 'Desert Bloom Suites',
                'manager' => 'Imane Belkacem',
                'email' => 'imane.belkacem@guesvia.dz',
                'city' => 'Ghardaia',
                'state' => 'paused',
                'days' => 24,
                // 14 of 18. Paused keeps real contract dates; the page shows
                // the word Paused in place of the date.
                'quotas' => [
                    'Reception' => [4, 5],
                    'Housekeeping' => [5, 6],
                    'Food Service' => [3, 4],
                    'Kitchen' => [2, 3],
                ],
            ],
            [
                'name' => 'Sunrise Dunes Hotel',
                'manager' => 'Karima Ouali',
                'email' => 'karima.ouali@guesvia.dz',
                'city' => 'Biskra',
                'state' => 'ended',
                'days' => 18,
                // 0 of 12: onboarded but nobody enrolled before it lapsed.
                'quotas' => [
                    'Reception' => [0, 4],
                    'Housekeeping' => [0, 3],
                    'Food Service' => [0, 3],
                    'Kitchen' => [0, 2],
                ],
            ],
            [
                'name' => 'Marina Bay Algiers',
                'manager' => 'Rachid Benali',
                'email' => 'rachid.benali@guesvia.dz',
                'city' => 'Algiers',
                'state' => 'pending',
                'days' => 60,
                'quotas' => [
                    'Reception' => [0, 6],
                    'Housekeeping' => [0, 6],
                    'Food Service' => [0, 4],
                ],
            ],
            [
                'name' => 'Old Medina Guesthouse',
                'manager' => 'Samira Larbi',
                'email' => 'samira.larbi@guesvia.dz',
                'city' => 'Constantine',
                'state' => 'archived',
                'days' => 90,
                'quotas' => [
                    'Reception' => [0, 3],
                    'Housekeeping' => [0, 3],
                ],
            ],
        ];
    }

    public function run(): void
    {
        $departments = $this->seedCatalogue();
        $employeeRoleId = Role::findByName(RoleEnum::Employee->value)->getKey();

        foreach ($this->portfolio() as $entry) {
            $hotel = $this->seedHotel($entry);

            foreach ($entry['quotas'] as $departmentName => [$used, $allowed]) {
                $department = $departments[$departmentName];

                SeatQuota::withoutGlobalScopes()->updateOrCreate(
                    ['hotel_id' => $hotel->id, 'department_id' => $department->id],
                    ['allowed_seats' => $allowed],
                );

                $this->seedEmployees($hotel, $department, $used, $employeeRoleId);
            }
        }
    }

    /**
     * The shared catalogue, keyed by name.
     *
     * @return array<string, Department>
     */
    private function seedCatalogue(): array
    {
        $departments = [];

        foreach (self::CATALOGUE as $position => $name) {
            $departments[$name] = Department::updateOrCreate(
                ['hotel_id' => null, 'slug' => Str::slug($name)],
                ['name' => $name, 'position' => $position, 'is_active' => true],
            );
        }

        return $departments;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function seedHotel(array $entry): Hotel
    {
        /** @var string $name */
        $name = $entry['name'];
        /** @var string $state */
        $state = $entry['state'];
        /** @var int $days */
        $days = $entry['days'];

        $factory = match ($state) {
            // active() last would overwrite the state, so pending comes after.
            'pending' => Hotel::factory()->active($days)->pending(),
            'expiring' => Hotel::factory()->expiring($days),
            'paused' => Hotel::factory()->active($days)->paused(),
            'ended' => Hotel::factory()->ended($days),
            'archived' => Hotel::factory()->archived(),
            default => Hotel::factory()->active($days),
        };

        $slug = Str::slug($name);
        $existing = Hotel::withoutGlobalScopes()->where('slug', $slug)->first();

        if ($existing !== null) {
            return $existing;
        }

        $employeeCount = array_sum(array_map(
            static fn (array $quota): int => (int) $quota[0],
            $entry['quotas'],
        ));
        $planSlug = $employeeCount <= 4 ? 'standard' : ($employeeCount <= 7 ? 'gold' : 'diamond');
        $planId = SubscriptionPlan::query()->where('slug', $planSlug)->firstOrFail()->id;

        return $factory->create([
            'name' => $name,
            'slug' => $slug,
            'city' => $entry['city'],
            'manager_name' => $entry['manager'],
            'manager_email' => $entry['email'],
            'contract_starts_on' => now()->subDays(60)->toDateString(),
            'subscription_plan_id' => $planId,
        ]);
    }

    /**
     * Create the active employee accounts the seat count reads from.
     *
     * Without these every hotel seeds at 0 seats used, because usage is
     * counted live and never stored (spec 0002, AC-6).
     */
    private function seedEmployees(Hotel $hotel, Department $department, int $count, int $employeeRoleId): void
    {
        if ($count === 0) {
            return;
        }

        $existing = User::query()
            ->where('hotel_id', $hotel->id)
            ->where('department_id', $department->id)
            ->count();

        if ($existing >= $count) {
            return;
        }

        $password = Hash::make('password');
        $rows = [];

        for ($i = $existing + 1; $i <= $count; $i++) {
            $name = fake()->name();

            // Deterministic per seat, so the mix is the same on every run:
            // ~85 % past the first-login screen, ~70 % consented to email
            // reminders, ~15 % never active, the rest active in the last two
            // weeks (spec 0003 G.7).
            $seat = ($hotel->id * 31 + $department->id * 7 + $i) % 100;
            $firstLoginDone = $seat < 85;
            $consented = $seat < 70;
            $everActive = $firstLoginDone && $seat % 100 >= 15;
            $daysAgo = $seat % 14;
            $lastActivity = $everActive ? now()->subDays($daysAgo)->subHours($seat % 9)->startOfMinute() : null;
            $createdAt = now()->subDays(20 + ($seat % 25));

            $rows[] = [
                'name' => $name,
                'username' => $this->uniqueUsername($name),
                'email' => sprintf('%s.%s.%d@guesvia.test', $hotel->slug, $department->slug, $i),
                'password' => $password,
                'hotel_id' => $hotel->id,
                'department_id' => $department->id,
                'status' => AccountStatus::Active->value,
                'participant_code' => User::generateParticipantCode(),
                'email_consent_at' => $consented && $firstLoginDone ? $createdAt->copy()->addDay() : null,
                'first_login_completed_at' => $firstLoginDone ? $createdAt->copy()->addDay() : null,
                'research_notice_acknowledged_at' => $firstLoginDone ? $createdAt->copy()->addDay() : null,
                'last_login_at' => $lastActivity?->copy()->subMinutes(25),
                'last_activity_at' => $lastActivity,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        User::insert($rows);

        // Attach the employee role in one statement rather than one per user:
        // this seeder creates 176 of them.
        $ids = User::query()
            ->where('hotel_id', $hotel->id)
            ->where('department_id', $department->id)
            ->pluck('id');

        DB::table('model_has_roles')->insertOrIgnore(
            $ids->map(fn (int $id): array => [
                'role_id' => $employeeRoleId,
                'model_type' => (new User)->getMorphClass(),
                'model_id' => $id,
            ])->all(),
        );
    }

    /**
     * Usernames handed out in this run, so the uniqueness check does not
     * have to hit the database for rows that are still buffered for insert.
     *
     * @var array<string, true>
     */
    private array $usernames = [];

    /**
     * `first.last`, lowercase `[a-z0-9.]`, with a `-2`, `-3` … suffix when
     * the name repeats (AUTH-01, spec 0003 B.1). Faker's honorifics and
     * suffixes ("Dr.", "Jr.", "DVM") are dropped so the handle is the name.
     */
    private function uniqueUsername(string $name): string
    {
        $parts = preg_split('/\s+/', Str::ascii(strtolower($name))) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => preg_match(
            '/^(mr|mrs|ms|miss|dr|prof|jr|sr|i|ii|iii|iv|v|dds|dvm|md|phd)\.?$/',
            $part,
        ) !== 1));

        $base = trim(preg_replace('/[^a-z0-9.]+/', '.', implode('.', $parts)) ?? '', '.');
        $base = preg_replace('/\.{2,}/', '.', $base) ?? $base;

        if (strlen($base) < 3) {
            $base = 'employee';
        }

        $base = substr($base, 0, 36);
        $candidate = $base;
        $suffix = 1;

        while (isset($this->usernames[$candidate]) || User::query()->where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        $this->usernames[$candidate] = true;

        return $candidate;
    }
}
