<?php

use App\Enums\Role;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HotelSignupController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\Learn\MessagesController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MeaningController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SupportMessageController;
use App\Http\Controllers\WelcomeSeenController;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', LandingPageController::class)->name('home');

// English or Arabic interface (I18N-02), for guests and signed-in users.
Route::put('locale', [LocaleController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('locale.update');

// Public Contact Us page (client decision 2026-09-26). Open to everyone,
// signed in or not; the form is throttled against abuse.
Route::get('contact', [ContactController::class, 'show'])->name('contact');
Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

// The customer's "payment submitted" page (client request 2026-09-27). An
// unguessable id, so it opens without signing in, and also once signed in.
Route::get('checkout/payment/{submission}', [CheckoutController::class, 'submitted'])
    ->whereUuid('submission')
    ->name('checkout.submitted');

Route::middleware('guest')->group(function () {
    Route::get('checkout/{plan:slug}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout/{plan:slug}', [CheckoutController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('checkout.store');
    Route::get('hotel-signup', [HotelSignupController::class, 'create'])->name('hotel-signup');
    Route::post('hotel-signup', [HotelSignupController::class, 'store'])->name('hotel-signup.store');
});

// Signed in is enough: the role decides which dashboard renders, so a user
// without the admin permissions gets the placeholder rather than a 403
// (spec 0001, AC-7). `verified` is dropped here for the same reason as in
// admin.php: User never implements MustVerifyEmail, so it guarded nothing.
Route::middleware(['auth', 'hotel.access'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('help', function (Request $request, LandingPageContentStore $content) {
        $page = $request->user('web')?->hasRole(Role::Employee->value)
            ? 'employee/Help'
            : 'Help';

        // How to reach the GHASIDO team (client request 2026-10-02).
        $support = (array) data_get($content->current(), 'support', []);

        return Inertia::render($page, [
            'support' => [
                'whatsapp' => (string) ($support['whatsapp_number'] ?? ''),
                'phone' => (string) ($support['phone'] ?? ''),
                'email' => (string) ($support['email'] ?? ''),
            ],
            // Their own messages to the team and the answers (client
            // request 2026-10-03).
            'conversations' => ContactMessage::query()
                ->where('user_id', $request->user('web')?->id)
                ->with('replies')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (ContactMessage $message): array => [
                    'id' => $message->id,
                    'topic' => $message->topic,
                    'message' => $message->topic !== null
                        ? (string) Str::of($message->message)->after('['.$message->topic.'] ')
                        : $message->message,
                    'sentAt' => $message->created_at?->toIso8601String() ?? '',
                    'replies' => $message->replies->map(fn (ContactReply $reply): array => [
                        'id' => $reply->id,
                        'body' => $reply->body,
                        'sentAt' => $reply->created_at?->toIso8601String() ?? '',
                    ])->values()->all(),
                ])->values()->all(),
        ]);
    })->name('help');
    Route::post('help/message', [SupportMessageController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('help.message');
    Route::post('notifications/{reminder}/read', [MessagesController::class, 'read'])
        ->name('notifications.read');

    // Private files (learner recordings, exports) are served only through
    // here, behind MediaAssetPolicy (PRIV-04, SEC-04; spec 0003 B.3). Any
    // signed in role may ask; the policy decides.
    Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');

    // Show Meaning on any English text (CTRL-01..03; client decision
    // 2026-09-26). The tapped button polls the same address while the
    // translation is queued, hence the generous limit.
    Route::post('meaning', MeaningController::class)
        ->middleware('throttle:120,1')
        ->name('meaning');

    // The one-time welcome animation after the first sign-in.
    Route::post('welcome/seen', WelcomeSeenController::class)->name('welcome.seen');
});

require __DIR__.'/admin.php';
require __DIR__.'/learn.php';
require __DIR__.'/owner.php';
require __DIR__.'/settings.php';
