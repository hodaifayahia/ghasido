<?php

namespace App\Http\Controllers\Admin\Messages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Messages\PreviewTemplateRequest;
use App\Http\Requests\Admin\Messages\StoreReminderTemplateRequest;
use App\Http\Requests\Admin\Messages\UpdateReminderTemplateRequest;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\Reminders\RecipientQuery;
use App\Services\Reminders\ReminderTemplateService;
use App\Services\Reminders\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Reminder templates: add, edit and preview (REM-04; spec 0003 Part D).
 */
class ReminderTemplateController extends Controller
{
    public function store(StoreReminderTemplateRequest $request, ReminderTemplateService $templates): RedirectResponse
    {
        $template = $templates->create($request->templateData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':template was added.', ['template' => $template->name]),
        ]);

        return back();
    }

    public function update(UpdateReminderTemplateRequest $request, ReminderTemplate $template, ReminderTemplateService $templates): RedirectResponse
    {
        $templates->update($template, $request->templateData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':template was updated.', ['template' => $template->name]),
        ]);

        return back();
    }

    /**
     * The subject and body with every variable filled in for one sample
     * employee, so the admin sees what a recipient will read. Plain JSON,
     * fetched by the editor while typing.
     */
    public function preview(PreviewTemplateRequest $request, TemplateRenderer $renderer): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $employees = RecipientQuery::forActor($actor);
        $recipientId = $request->recipientId();

        $sample = $recipientId === null
            ? null
            : (clone $employees)->where('users.id', $recipientId)->first();

        $sample ??= $employees->first();

        if ($sample === null) {
            return response()->json([
                'sample' => null,
                'subject' => $request->subject(),
                'body' => $request->body(),
                'values' => [],
            ]);
        }

        return response()->json([
            'sample' => [
                'id' => $sample->id,
                'name' => $sample->name,
                'hotel' => $sample->hotel?->name,
                'department' => $sample->department?->name,
            ],
            'subject' => $renderer->render($request->subject(), $sample),
            'body' => $renderer->render($request->body(), $sample),
            'values' => $renderer->values($sample),
        ]);
    }
}
