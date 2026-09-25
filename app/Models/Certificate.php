<?php

namespace App\Models;

use App\Enums\CertificateType;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A certificate issued to an employee for a course (CERT-02, CERT-03,
 * CERT-06, CERT-07, spec 0003 B.6).
 *
 * Issue, revoke and re-issue are separate rows: revoking sets `revoked_at`
 * and keeps everything. `verification_id` is the public identifier a lookup
 * would use, generated on creation when none is given.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property CertificateType $type
 * @property string $verification_id
 * @property Carbon $issued_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'course_id',
    'type',
    'verification_id',
    'issued_at',
    'revoked_at',
])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate): void {
            if (blank($certificate->getAttribute('verification_id'))) {
                $certificate->verification_id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Certificates that are still valid.
     *
     * @param  Builder<Certificate>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
