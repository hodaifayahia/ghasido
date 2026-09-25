<?php

namespace App\Http\Controllers\Admin\Messages;

use App\Http\Controllers\Controller;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\Reminders\ReminderDrafter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * "Draft with AI" for reminder templates (spec 0005 §4.2). Only whoever
 * may write templates may ask for a draft (ReminderTemplatePolicy::create:
 * the Super Admin); the draft is queued and polled, never saved by itself.
 */
class ReminderDraftController extends Controller
{
    public function __construct(private readonly ReminderDrafter $drafter) {}

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ReminderTemplate::class);

        $validated = $request->validate([
            'purpose' => ['required', 'string', 'min:5', 'max:300'],
            'tone' => ['required', Rule::in(ReminderDrafter::TONES)],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $this->drafter->request($actor, (string) $validated['purpose'], (string) $validated['tone']);

        return response()->json($this->drafter->state($actor), 202);
    }

    public function show(Request $request): JsonResponse
    {
        Gate::authorize('create', ReminderTemplate::class);

        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->drafter->state($actor));
    }
}
