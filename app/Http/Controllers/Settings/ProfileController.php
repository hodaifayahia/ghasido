<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            // Admin accounts fill the full contact profile (owner request
            // 2026-09-25); the fields are not in the shared `auth.user`.
            'adminProfile' => $user->needsAdminProfile() ? [
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'phone' => $user->phone,
                'address' => $user->address,
                'incomplete' => $user->adminProfileIncomplete(),
            ] : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $wasIncomplete = $user->adminProfileIncomplete();
        $data = $request->validated();

        if ($user->needsAdminProfile()) {
            // The display name used across the app follows the two parts.
            $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        // Sent here to complete the profile: back to work once it is done.
        return $wasIncomplete && ! $user->adminProfileIncomplete()
            ? to_route('dashboard')
            : to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function user(Request $request): User
    {
        $user = $request->user('web');
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
