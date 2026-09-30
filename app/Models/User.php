<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountStatus;
use App\Enums\EnglishLevel;
use App\Enums\Role;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Services\Learning\JourneyService;
use App\Support\Locales;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string|null $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $address
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $hotel_id
 * @property int|null $department_id
 * @property AccountStatus $status
 * @property Carbon|null $email_consent_at
 * @property Carbon|null $first_login_completed_at
 * @property Carbon|null $research_notice_acknowledged_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $welcomed_at
 * @property string|null $locale
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $training_started_at
 * @property Carbon|null $training_completed_at
 * @property string|null $participant_code
 * @property string|null $cohort
 * @property int|null $created_by
 * @property int $ai_points_allocated
 * @property EnglishLevel|null $english_level
 * @property Carbon|null $english_level_assessed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read IndividualSubscription|null $individualSubscription
 */
#[Fillable([
    'name',
    'username',
    'email',
    'first_name',
    'last_name',
    'phone',
    'address',
    'password',
    'hotel_id',
    'department_id',
    'status',
    'email_consent_at',
    'first_login_completed_at',
    'research_notice_acknowledged_at',
    'last_login_at',
    'last_activity_at',
    'training_started_at',
    'training_completed_at',
    'participant_code',
    'cohort',
    'created_by',
    'ai_points_allocated',
    'english_level',
    'english_level_assessed_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The language of the emails this user receives, password reset
     * included (I18N-02): the one chosen in the interface, else English.
     */
    public function preferredLocale(): string
    {
        return Locales::isSupported($this->locale) ? (string) $this->locale : Locales::DEFAULT;
    }

    /**
     * The department a manager has chosen to train in for this request
     * (client decision 2026-09-23).
     *
     * Transient: set by ResolveTrainingDepartment on the learner routes,
     * never persisted and never serialized (it is a declared property, not an
     * Eloquent attribute, so toArray() never carries it). Null for a real
     * employee, whose department_id is already their learning department.
     */
    public ?int $trainingDepartmentId = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'status' => AccountStatus::class,
            'ai_points_allocated' => 'integer',
            'email_consent_at' => 'datetime',
            'first_login_completed_at' => 'datetime',
            'research_notice_acknowledged_at' => 'datetime',
            'last_login_at' => 'datetime',
            'welcomed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'training_started_at' => 'datetime',
            'training_completed_at' => 'datetime',
            'english_level' => EnglishLevel::class,
            'english_level_assessed_at' => 'datetime',
        ];
    }

    /**
     * Give this user exactly one role, replacing any it already holds.
     *
     * A user holds at most one role (spec 0001, invariant 1), so this is the
     * only place a role is assigned: syncRoles() replaces rather than appends,
     * which assignRole() would not.
     */
    /**
     * The roles that must complete the admin contact profile (owner request
     * 2026-09-25): first and last name, email, phone and address. Learners
     * never are (PRIV-03).
     *
     * @var list<Role>
     */
    public const array ADMIN_PROFILE_ROLES = [Role::SuperAdmin, Role::Admin];

    public function needsAdminProfile(): bool
    {
        return $this->hasAnyRole(array_map(
            static fn (Role $role): string => $role->value,
            self::ADMIN_PROFILE_ROLES,
        ));
    }

    /**
     * An admin who has not filled every contact field yet; the app sends
     * them to their profile until they do.
     */
    public function adminProfileIncomplete(): bool
    {
        if (! $this->needsAdminProfile()) {
            return false;
        }

        foreach ([$this->first_name, $this->last_name, $this->email, $this->phone, $this->address] as $value) {
            if ($value === null || trim($value) === '') {
                return true;
            }
        }

        return false;
    }

    public function setRole(Role $role): self
    {
        $this->syncRoles([$role->value]);

        return $this;
    }

    /**
     * The name of this user's role, or null when they hold none.
     *
     * Feeds `auth.user.role` in the shared Inertia props (spec 0001, AC-5).
     */
    public function roleName(): ?string
    {
        /** @var string|null $name */
        $name = $this->getRoleNames()->first();

        return $name;
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * An individual subscriber's own configuration: a learner with no hotel
     * (user request 2026-09-25).
     *
     * @return HasOne<IndividualSubscription, $this>
     */
    public function individualSubscription(): HasOne
    {
        return $this->hasOne(IndividualSubscription::class);
    }

    /** A learner with no hotel, on their own subscription. */
    public function isIndividual(): bool
    {
        return $this->hotel_id === null && $this->individualSubscription !== null;
    }

    /**
     * The admin or manager who created this account (AUTH-03).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<BlockCompletion, $this> */
    public function blockCompletions(): HasMany
    {
        return $this->hasMany(BlockCompletion::class);
    }

    /** @return HasMany<LessonCompletion, $this> */
    public function lessonCompletions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    /** @return HasMany<TestAttempt, $this> */
    public function testAttempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    /** @return HasMany<Attempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /** @return HasMany<RoleplayAttempt, $this> */
    public function roleplayAttempts(): HasMany
    {
        return $this->hasMany(RoleplayAttempt::class);
    }

    /** @return HasMany<PhrasebookItem, $this> */
    public function phrasebookItems(): HasMany
    {
        return $this->hasMany(PhrasebookItem::class);
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /** @return HasMany<VoiceRecording, $this> */
    public function voiceRecordings(): HasMany
    {
        return $this->hasMany(VoiceRecording::class);
    }

    /** @return HasMany<Certificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    // ----------------------------------------------------------------- scopes

    /**
     * Learner accounts only (the employee role).
     *
     * @param  Builder<User>  $query
     */
    public function scopeEmployees(Builder $query): void
    {
        $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::Employee->value));
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', AccountStatus::Active->value);
    }

    // ---------------------------------------------------------------- journey

    /**
     * Has this learner been through the first-login screen (AUTH-04)?
     *
     * Until true, EnsureFirstLoginCompleted keeps them on that screen.
     */
    public function hasCompletedFirstLogin(): bool
    {
        return $this->first_login_completed_at !== null;
    }

    /**
     * May a reminder email be sent to this learner (REM-05, PRIV-02)?
     *
     * Consent is a dated timestamp that revocation clears, so the answer is
     * always the current one. No email means nothing to send to.
     */
    public function consentsToReminders(): bool
    {
        return $this->email_consent_at !== null && $this->email !== null;
    }

    /**
     * Gate 1 of the journey (JOURNEY-01): has this learner submitted a
     * Pre-test they were actually entitled to sit?
     *
     * Goes through Test::scopeForLearner() so a submitted attempt on a test
     * that is no longer theirs (a department change, an unpublished test)
     * does not unlock anything.
     */
    public function hasSubmittedPreTest(): bool
    {
        return $this->oncePerRequest('pre-test-submitted', fn (): bool => $this->testAttempts()
            ->where('status', TestAttemptStatus::Submitted->value)
            ->whereHas('test', function (Builder $test): void {
                /** @var Builder<Test> $test */
                $test->ofType(TestType::Pre)->forLearner($this);
            })
            ->exists());
    }

    /**
     * Does a published Pre-test exist for this learner (their department,
     * shared or their hotel's)? Through Test::scopeForLearner(), like
     * hasSubmittedPreTest(), so the two can never disagree.
     */
    public function hasPreTestToSit(): bool
    {
        return $this->oncePerRequest('pre-test-to-sit', fn (): bool => Test::query()->forLearner($this)->ofType(TestType::Pre)->exists());
    }

    /**
     * The gate checks run many times in one HTTP request (middleware,
     * policies, the shared journey prop): answer each once per request.
     * Kept on the request, never on the model, so the next request, a test
     * request or a queued job reads fresh rows. A submitted test always
     * redirects, so no request writes a sitting and then reads the gate.
     */
    private function oncePerRequest(string $key, \Closure $compute): bool
    {
        $request = request();
        $key = 'user.'.$this->id.'.'.$key;

        if ($request->route() === null) {
            return (bool) $compute();
        }

        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, (bool) $compute());
        }

        return (bool) $request->attributes->get($key);
    }

    /**
     * Gate 1 as the lessons read it (JOURNEY-01, client decision
     * 2026-09-23): lessons unlock once the Pre-test is submitted, or at once
     * when no published Pre-test exists for this learner's department. The
     * gate only applies when there is a Pre-test to sit.
     */
    public function lessonsUnlocked(): bool
    {
        return $this->hasSubmittedPreTest() || ! $this->hasPreTestToSit();
    }

    /**
     * Completed lessons over the published lessons of this learner's
     * department courses, as a whole percentage; 0 with none (DATA-07, the
     * `{{progress}}` reminder variable). Delegates to JourneyService so the
     * reminders, the dashboard and the learner shell agree.
     */
    public function progressPercent(): int
    {
        return app(JourneyService::class)->progressPercent($this);
    }

    /**
     * Gate 2 of the journey (JOURNEY-04): may this learner sit the Post-test?
     * The condition lives in JourneyService, written once.
     */
    public function postTestUnlocked(): bool
    {
        return app(JourneyService::class)->postTestUnlocked($this);
    }

    /**
     * Does this account occupy an employee seat?
     *
     * Only active employees count. Managers do not consume employee seats, and
     * a deactivated account frees its seat without anything being deleted
     * (spec 0002, invariant 9; DATA-10).
     */
    public function occupiesSeat(): bool
    {
        return $this->status === AccountStatus::Active
            && $this->hasRole(Role::Employee->value);
    }

    // ---------------------------------------------------------- self-training

    /**
     * The department whose curriculum this user learns from (client decision
     * 2026-09-23).
     *
     * A real employee learns their own department. A manager has none, so
     * they pick one to train in and ResolveTrainingDepartment puts the choice
     * on $trainingDepartmentId for the request. This is the one value the
     * learner scopes read (Course/Test/AiScenario::scopeForLearner), so a
     * manager and an employee resolve content the same way.
     */
    public function learningDepartmentId(): ?int
    {
        return $this->department_id ?? $this->trainingDepartmentId;
    }

    /**
     * May this account work through the training itself (client decision
     * 2026-09-23)?
     *
     * Employees always; a manager as well as running their hotel; the Super
     * Admin for employee-preview (ROLE-03). Custom roles are not yet
     * assignable to accounts, so a role check is enough and cannot throw on a
     * not-yet-seeded permission.
     */
    public function canSelfTrain(): bool
    {
        return $this->hasAnyRole([
            Role::Employee->value,
            Role::Manager->value,
            Role::SuperAdmin->value,
        ]);
    }

    /**
     * A manager who has not yet chosen a department to train in. The learner
     * routes send them to the department chooser first, the same way the
     * first-login gate works for an employee.
     */
    public function needsTrainingDepartment(): bool
    {
        return $this->department_id === null
            && $this->trainingDepartmentId === null
            && $this->hasRole(Role::Manager->value);
    }

    /**
     * A fresh, unused participant code: "P-" plus six uppercase
     * alphanumerics (REP-07). The anonymised export prints this instead of a
     * name, so it is generated once when the employee account is created and
     * never changed.
     */
    public static function generateParticipantCode(): string
    {
        do {
            $code = 'P-'.Str::upper(Str::random(6));
        } while (static::query()->where('participant_code', $code)->exists());

        return $code;
    }

    /**
     * `role` as a serialized attribute, for the shared Inertia props.
     *
     * Appended per instance in HandleInertiaRequests rather than declared in
     * #[Appends], so reading a role never becomes a hidden query on every
     * other place a User is serialized.
     *
     * @return Attribute<string|null, never>
     */
    protected function role(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->roleName());
    }

    /**
     * Every permission name this user holds through their role.
     *
     * Feeds `auth.permissions`, which the sidebar filter reads (AC-5, AC-6).
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        /** @var list<string> $names */
        $names = $this->getAllPermissions()->pluck('name')->values()->all();

        return $names;
    }
}
