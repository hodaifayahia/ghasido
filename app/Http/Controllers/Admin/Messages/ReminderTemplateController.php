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
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Reminder templates: add, edit, delete and preview (REM-04; spec 0003
 * Part D).
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
     * Delete a template. One that an automation rule still sends is refused
     * with the rules' names, never detached or cascaded: a rule cannot run
     * without a template, and deleting it would silently delete those rules
     * too (REM-03, REM-04).
     */
    public function destroy(ReminderTemplate $template, ReminderTemplateService $templates): RedirectResponse
    {
        Gate::authorize('delete', $template);

        $rules = $templates->rulesUsing($template);

        if ($rules !== []) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __(':template is still used by these automation rules: :rules. Point them at another template or delete them first.', [
                    'template' => $template->name,
                    'rules' => implode(', ', $rules),
                ]),
            ]);

            return back();
        }

        $templates->delete($template);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':template was deleted.', ['template' => $template->name]),
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
