<?php

use App\Http\Middleware\EnsureAdminProfileCompleted;
use App\Http\Middleware\EnsureFirstLoginCompleted;
use App\Http\Middleware\EnsureHotelAccess;
use App\Http\Middleware\EnsureOwnerAuthenticated;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTrainingDepartment;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'locale']);

        $middleware->web(append: [
            HandleAppearance::class,
            // Before Inertia shares its props, so they are in the chosen
            // interface language (I18N-02).
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Admin accounts complete their contact profile first (owner
            // request 2026-09-25; spec 0007, D12).
            EnsureAdminProfileCompleted::class,
        ]);

        // Authorization is decided at the route boundary, server side
        // (ROLE-01, SEC-01). `permission:` is the one the app uses: it calls
        // canAny(), so the Gate::before Super Admin override applies to it.
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            // The learner gates (spec 0003 Part E): a session may not outlive
            // its hotel's access (AUTH-08, SUB-05), and no learner route opens
            // before the first-login screen is done (AUTH-04).
            'hotel.access' => EnsureHotelAccess::class,
            'first-login' => EnsureFirstLoginCompleted::class,
            // A manager learns as an employee in a department they choose
            // (client decision 2026-09-23); this resolves that choice.
            'training.department' => ResolveTrainingDepartment::class,
            // The platform owner's console (spec 0007): the `owner` guard.
            'owner' => EnsureOwnerAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
