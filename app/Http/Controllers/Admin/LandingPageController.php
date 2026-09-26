<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LandingPageController extends Controller
{
    public function edit(Request $request, LandingPageContentStore $content): Response
    {
        abort_unless($request->user()?->can(Permission::LandingManage->value), 403);

        return Inertia::render('settings/LandingPage', [
            'content' => $content->current(),
            'contactMessages' => ContactMessage::query()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (ContactMessage $message): array => [
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
                ])->values()->all(),
        ]);
    }

    public function markContactMessageRead(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::LandingManage->value), 403);

        $contactMessage->forceFill(['read_at' => $contactMessage->read_at ?? now()])->save();

        return back();
    }

    public function update(Request $request, LandingPageContentStore $content): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::LandingManage->value), 403);

        $validated = $request->validate([
            'content' => ['required', 'array:navigation,roles,journey,hero,why_us,about,features,ai,pricing,checkout,call_to_action,footer,support,contact'],
            'content.navigation' => ['required', 'array:why_us,about,platform,ai_practice,roles,pricing,login,get_started,open_dashboard,contact'],
            'content.navigation.contact' => ['required', 'string', 'max:40'],
            'content.navigation.why_us' => ['required', 'string', 'max:40'],
            'content.navigation.about' => ['required', 'string', 'max:40'],
            'content.navigation.platform' => ['required', 'string', 'max:40'],
            'content.navigation.ai_practice' => ['required', 'string', 'max:40'],
            'content.navigation.roles' => ['required', 'string', 'max:40'],
            'content.navigation.pricing' => ['required', 'string', 'max:40'],
            'content.navigation.login' => ['required', 'string', 'max:40'],
            'content.navigation.get_started' => ['required', 'string', 'max:40'],
            'content.navigation.open_dashboard' => ['required', 'string', 'max:40'],
            'content.roles' => ['required', 'array:eyebrow,title,description,items'],
            'content.roles.eyebrow' => ['required', 'string', 'max:100'],
            'content.roles.title' => ['required', 'string', 'max:180'],
            'content.roles.description' => ['required', 'string', 'max:600'],
            'content.roles.items' => ['required', 'array', 'min:1', 'max:8'],
            'content.roles.items.*' => ['required', 'array:title,description,capabilities'],
            'content.roles.items.*.title' => ['required', 'string', 'max:120'],
            'content.roles.items.*.description' => ['required', 'string', 'max:400'],
            'content.roles.items.*.capabilities' => ['required', 'array', 'min:1', 'max:8'],
            'content.roles.items.*.capabilities.*' => ['required', 'string', 'max:160'],
            'content.journey' => ['required', 'array:eyebrow,title,description,steps'],
            'content.journey.eyebrow' => ['required', 'string', 'max:100'],
            'content.journey.title' => ['required', 'string', 'max:180'],
            'content.journey.description' => ['required', 'string', 'max:600'],
            'content.journey.steps' => ['required', 'array', 'min:1', 'max:8'],
            'content.journey.steps.*' => ['required', 'array:title,description'],
            'content.journey.steps.*.title' => ['required', 'string', 'max:120'],
            'content.journey.steps.*.description' => ['required', 'string', 'max:400'],
            'content.hero' => ['required', 'array:eyebrow,title,description,primary_cta,secondary_cta,image_alt,proof_points'],
            'content.hero.eyebrow' => ['required', 'string', 'max:100'],
            'content.hero.title' => ['required', 'string', 'max:180'],
            'content.hero.description' => ['required', 'string', 'max:600'],
            'content.hero.primary_cta' => ['required', 'string', 'max:60'],
            'content.hero.secondary_cta' => ['required', 'string', 'max:60'],
            'content.hero.image_alt' => ['required', 'string', 'max:240'],
            'content.hero.proof_points' => ['required', 'array', 'size:3'],
            'content.hero.proof_points.*' => ['required', 'string', 'max:60'],
            'content.why_us' => ['required', 'array:eyebrow,title,description,items'],
            'content.why_us.eyebrow' => ['required', 'string', 'max:100'],
            'content.why_us.title' => ['required', 'string', 'max:180'],
            'content.why_us.description' => ['required', 'string', 'max:600'],
            'content.why_us.items' => ['required', 'array', 'min:1', 'max:6'],
            'content.why_us.items.*' => ['required', 'array:title,description'],
            'content.why_us.items.*.title' => ['required', 'string', 'max:120'],
            'content.why_us.items.*.description' => ['required', 'string', 'max:400'],
            'content.about' => ['required', 'array:eyebrow,title,description,learner_note,image_alt'],
            'content.about.eyebrow' => ['required', 'string', 'max:100'],
            'content.about.title' => ['required', 'string', 'max:180'],
            'content.about.description' => ['required', 'string', 'max:1000'],
            'content.about.learner_note' => ['required', 'string', 'max:400'],
            'content.about.image_alt' => ['required', 'string', 'max:240'],
            'content.features' => ['required', 'array:eyebrow,title,description,items'],
            'content.features.eyebrow' => ['required', 'string', 'max:100'],
            'content.features.title' => ['required', 'string', 'max:180'],
            'content.features.description' => ['required', 'string', 'max:600'],
            'content.features.items' => ['required', 'array', 'min:1', 'max:8'],
            'content.features.items.*' => ['required', 'array:title,description'],
            'content.features.items.*.title' => ['required', 'string', 'max:120'],
            'content.features.items.*.description' => ['required', 'string', 'max:400'],
            'content.ai' => ['required', 'array:eyebrow,title,description,review_note,items'],
            'content.ai.eyebrow' => ['required', 'string', 'max:100'],
            'content.ai.title' => ['required', 'string', 'max:180'],
            'content.ai.description' => ['required', 'string', 'max:600'],
            'content.ai.review_note' => ['required', 'string', 'max:300'],
            'content.ai.items' => ['required', 'array', 'min:1', 'max:8'],
            'content.ai.items.*' => ['required', 'array:title,description'],
            'content.ai.items.*.title' => ['required', 'string', 'max:120'],
            'content.ai.items.*.description' => ['required', 'string', 'max:400'],
            'content.pricing' => ['required', 'array:eyebrow,title,description,monthly_label,employees_label,ai_points_label,button_text,featured_label,inclusions,footnote,region_algeria,region_international'],
            'content.pricing.region_algeria' => ['required', 'string', 'max:40'],
            'content.pricing.region_international' => ['required', 'string', 'max:40'],
            'content.pricing.eyebrow' => ['required', 'string', 'max:100'],
            'content.pricing.title' => ['required', 'string', 'max:180'],
            'content.pricing.description' => ['required', 'string', 'max:600'],
            'content.pricing.monthly_label' => ['required', 'string', 'max:40'],
            'content.pricing.employees_label' => ['required', 'string', 'max:60'],
            'content.pricing.ai_points_label' => ['required', 'string', 'max:80'],
            'content.pricing.button_text' => ['required', 'string', 'max:60'],
            'content.pricing.featured_label' => ['required', 'string', 'max:60'],
            'content.pricing.inclusions' => ['required', 'array', 'min:1', 'max:12'],
            'content.pricing.inclusions.*' => ['required', 'string', 'max:160'],
            'content.pricing.footnote' => ['required', 'string', 'max:400'],
            'content.checkout' => ['required', 'array:eyebrow,title,description,summary_title,form_title,payment_title,payment_description,submit_button,approval_note,back_to_plans'],
            'content.checkout.eyebrow' => ['required', 'string', 'max:100'],
            'content.checkout.title' => ['required', 'string', 'max:180'],
            'content.checkout.description' => ['required', 'string', 'max:600'],
            'content.checkout.summary_title' => ['required', 'string', 'max:100'],
            'content.checkout.form_title' => ['required', 'string', 'max:100'],
            'content.checkout.payment_title' => ['required', 'string', 'max:100'],
            'content.checkout.payment_description' => ['required', 'string', 'max:600'],
            'content.checkout.submit_button' => ['required', 'string', 'max:80'],
            'content.checkout.approval_note' => ['required', 'string', 'max:300'],
            'content.checkout.back_to_plans' => ['required', 'string', 'max:80'],
            'content.call_to_action' => ['required', 'array:eyebrow,title,description,button_text'],
            'content.call_to_action.eyebrow' => ['required', 'string', 'max:100'],
            'content.call_to_action.title' => ['required', 'string', 'max:180'],
            'content.call_to_action.description' => ['required', 'string', 'max:600'],
            'content.call_to_action.button_text' => ['required', 'string', 'max:60'],
            'content.footer' => ['required', 'array:tagline'],
            'content.footer.tagline' => ['required', 'string', 'max:160'],
            'content.support' => ['required', 'array:whatsapp_number,phone,email'],
            'content.support.phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9().\s-]{6,}$/'],
            'content.support.email' => ['nullable', 'string', 'email', 'max:180'],
            'content.contact' => ['required', 'array:eyebrow,title,description,form_title,submit_button,success_message,enterprise_title,enterprise_subtitle,enterprise_description,enterprise_points,enterprise_button,enterprise_note'],
            'content.contact.eyebrow' => ['required', 'string', 'max:100'],
            'content.contact.title' => ['required', 'string', 'max:180'],
            'content.contact.description' => ['required', 'string', 'max:600'],
            'content.contact.form_title' => ['required', 'string', 'max:100'],
            'content.contact.submit_button' => ['required', 'string', 'max:60'],
            'content.contact.success_message' => ['required', 'string', 'max:300'],
            'content.contact.enterprise_title' => ['required', 'string', 'max:100'],
            'content.contact.enterprise_subtitle' => ['required', 'string', 'max:120'],
            'content.contact.enterprise_description' => ['required', 'string', 'max:400'],
            'content.contact.enterprise_points' => ['required', 'array', 'min:1', 'max:6'],
            'content.contact.enterprise_points.*' => ['required', 'string', 'max:120'],
            'content.contact.enterprise_button' => ['required', 'string', 'max:60'],
            'content.contact.enterprise_note' => ['required', 'string', 'max:160'],
            'content.support.whatsapp_number' => [
                'nullable',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $digits = preg_replace('/\D/', '', $value) ?? '';

                    if (! preg_match('/^\+?[0-9().\s-]+$/', $value) || strlen($digits) < 7 || strlen($digits) > 15) {
                        $fail('Enter a valid WhatsApp number with 7 to 15 digits.');
                    }
                },
            ],
        ]);

        $validated['content']['support']['whatsapp_number'] = trim((string) ($validated['content']['support']['whatsapp_number'] ?? ''));
        $validated['content']['support']['phone'] = trim((string) ($validated['content']['support']['phone'] ?? ''));
        $validated['content']['support']['email'] = trim((string) ($validated['content']['support']['email'] ?? ''));

        /** @var User $actor */
        $actor = $request->user();
        $content->save($validated['content'], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Landing page content saved.']);

        return back();
    }
}
