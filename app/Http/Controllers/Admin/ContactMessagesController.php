<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contact Requests (user request 2026-09-26): everyone who wrote from the
 * public Contact Us page, with the details they left, on its own sidebar
 * entry. Same capability as Website Management, where the business email
 * these messages are forwarded to is set (SEC-01).
 */
final class ContactMessagesController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::LandingManage->value);

        $onlyNew = $request->query('show') === 'new';

        $messages = ContactMessage::query()
            ->when($onlyNew, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ContactMessage $message): array => [
                'id' => $message->id,
                'name' => $message->name,
                'email' => $message->email,
                'phone' => $message->phone,
                'organisation' => $message->organisation,
                'employees' => $message->employees,
                'message' => $message->message,
                'read' => $message->read_at !== null,
                'sentAt' => $message->created_at?->toIso8601String(),
                'readUrl' => route('contact-messages.read', $message),
            ]);

        return Inertia::render('admin/ContactMessages', [
            'messages' => $messages,
            'show' => $onlyNew ? 'new' : 'all',
            'counts' => [
                'all' => ContactMessage::query()->count(),
                'new' => ContactMessage::query()->whereNull('read_at')->count(),
            ],
        ]);
    }
}
