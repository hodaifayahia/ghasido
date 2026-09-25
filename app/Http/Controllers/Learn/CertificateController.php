<?php

namespace App\Http\Controllers\Learn;

use App\Enums\CertificateType;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Learning\JourneyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The employee's certificate (JOURNEY-05, CERT-02..04, CERT-06, CERT-07;
 * spec 0003 Part E).
 *
 * A print-ready page rather than a PDF (the PDF package is a pending
 * decision). The certificate row is issued the first time the page is
 * opened while the condition holds: a submitted Post-test. Its type follows
 * the test's pass mark when one is set (completion above it, participation
 * below), and is completion when no mark is configured. That rule stands
 * in for the admin-defined condition until the CMS exposes it (CERT-04).
 */
class CertificateController extends Controller
{
    public function __construct(private readonly JourneyService $journey) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $journey = $this->journey->summary($user);
        $certificate = $this->currentCertificate($user);

        if ($certificate === null && $journey['certificateAvailable']) {
            $certificate = $this->issue($user);
        }

        return Inertia::render('employee/Certificate', [
            'certificate' => $certificate === null ? null : [
                'id' => $certificate->id,
                'type' => $certificate->type->value,
                'typeLabel' => $certificate->type->label(),
                'verificationId' => $certificate->verification_id,
                'issuedAt' => $certificate->issued_at->toIso8601String(),
                'course' => ['id' => $certificate->course_id, 'title' => $certificate->course->title],
                'employee' => ['name' => $user->name],
                'hotel' => $user->hotel?->name,
                'department' => $user->department?->name,
            ],
            'eligibility' => [
                'available' => $journey['certificateAvailable'],
                'preTestSubmitted' => $journey['preTestSubmitted'],
                'lessonsCompleted' => $journey['lessonsCompleted'],
                'lessonsTotal' => $journey['lessonsTotal'],
                'postTestUnlocked' => $journey['postTestUnlocked'],
                'postTestSubmitted' => $this->journey->postTestSubmitted($user),
            ],
            'journey' => $journey,
        ]);
    }

    private function currentCertificate(User $user): ?Certificate
    {
        return $user->certificates()
            ->valid()
            ->with('course')
            ->latest('issued_at')
            ->first();
    }

    /**
     * Issue the certificate for the learner's first course, typed by the
     * Post-test outcome (CERT-02, CERT-04).
     */
    private function issue(User $user): ?Certificate
    {
        $course = Course::query()->forLearner($user)->orderBy('position')->orderBy('id')->first();

        if ($course === null) {
            return null;
        }

        $attempt = TestAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', TestAttemptStatus::Submitted->value)
            ->whereHas('test', function (Builder $test) use ($user): void {
                /** @var Builder<Test> $test */
                $test->ofType(TestType::Post)->forLearner($user);
            })
            ->with('test')
            ->latest('submitted_at')
            ->first();

        $passed = $attempt?->scoreSummary()['passed'];

        $certificate = new Certificate([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => $passed === false ? CertificateType::Participation : CertificateType::Completion,
            'issued_at' => now(),
        ]);

        $certificate->save();

        AuditLog::record($certificate, 'certificate.issued', ['type' => $certificate->type->value]);

        return $certificate->load('course');
    }
}
