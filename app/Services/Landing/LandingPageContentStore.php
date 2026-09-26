<?php

namespace App\Services\Landing;

use App\Models\AuditLog;
use App\Models\LandingPageContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Defaults and the persisted copy for the public landing page. Content is
 * stored by section so the CMS can update text without a code deployment.
 */
final class LandingPageContentStore
{
    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'navigation' => [
                'why_us' => 'Why GHASIDO',
                'about' => 'About',
                'platform' => 'Platform',
                'ai_practice' => 'AI practice',
                'roles' => 'Who it is for',
                'pricing' => 'Plans',
                'login' => 'Log in',
                'get_started' => 'Get started',
                'open_dashboard' => 'Open dashboard',
                'contact' => 'Contact us',
            ],
            'hero' => [
                'eyebrow' => 'English for every hotel team',
                'title' => 'Hotel English your team can use on their next shift.',
                'description' => 'GHASIDO brings lessons, assessments, AI guest role-play and measurable progress into one clear training path for every hotel department.',
                'primary_cta' => 'See how it works',
                'secondary_cta' => 'View plans',
                'image_alt' => 'GHASIDO training screens, with the admin dashboard and an employee pre-test on a phone',
                'proof_points' => ['Built for hotel teams', 'Works beautifully on mobile', 'AI practice with purpose'],
            ],
            'roles' => [
                'eyebrow' => 'One platform, three clear experiences',
                'title' => 'The right tools for every role.',
                'description' => 'Each person sees a focused workspace for the decisions and tasks that belong to them.',
                'items' => [
                    [
                        'title' => 'Hotel Admin',
                        'description' => 'Coordinate one hotel’s departments, employee accounts and learning reports from a secure workspace.',
                        'capabilities' => ['Create employees within the plan', 'Manage departments and accounts', 'Review and export hotel reports'],
                    ],
                    [
                        'title' => 'Hotel Manager',
                        'description' => 'Support the existing team with employee updates, AI point allocation and focused reminders.',
                        'capabilities' => ['Update existing employees', 'Allocate AI practice points', 'Send focused reminders'],
                    ],
                    [
                        'title' => 'Employee',
                        'description' => 'Follow a simple mobile learning path made for the conversations that happen at work.',
                        'capabilities' => ['Learn by department', 'Practise with voice and AI', 'Complete tests and earn a certificate'],
                    ],
                ],
            ],
            'journey' => [
                'eyebrow' => 'A learning path that stays clear',
                'title' => 'From first assessment to confident guest conversations.',
                'description' => 'Every learner always knows what comes next, and progress is saved throughout the journey.',
                'steps' => [
                    ['title' => 'Pre-test', 'description' => 'Start with a clear picture of current skills.'],
                    ['title' => 'Department training', 'description' => 'Work through short lessons for the employee’s role.'],
                    ['title' => 'AI practice', 'description' => 'Try realistic guest conversations in a safe space.'],
                    ['title' => 'Post-test & certificate', 'description' => 'Measure progress and recognise completion.'],
                ],
            ],
            'why_us' => [
                'eyebrow' => 'Why GHASIDO',
                'title' => 'Training made for the hotel floor.',
                'description' => 'Short lessons help staff practise the English they need during a real shift.',
                'items' => [
                    ['title' => 'Built for each department', 'description' => 'Lessons reflect the conversations teams have with guests every day.'],
                    ['title' => 'Easy to fit into a shift', 'description' => 'Small steps make it practical to learn on a phone between tasks.'],
                    ['title' => 'Progress you can follow', 'description' => 'Managers can see participation and learning progress across their hotel.'],
                ],
            ],
            'about' => [
                'eyebrow' => 'About GHASIDO',
                'title' => 'A clearer way to learn hotel English.',
                'description' => 'GHASIDO helps hotels build staff confidence with job-specific lessons, practice and measurable progress.',
                'learner_note' => 'English comes first, with a simple Arabic meaning available when the learner asks for it.',
                'image_alt' => 'An employee using the GHASIDO pre-test on a phone',
            ],
            'features' => [
                'eyebrow' => 'The complete platform',
                'title' => 'Built to make training easier to run and easier to finish.',
                'description' => 'GHASIDO connects content, people and evidence of progress without adding administrative clutter.',
                'items' => [
                    ['title' => 'Create hotel-specific learning without code', 'description' => 'Build and organise department lessons, activities, tests, audio and examples from one visual content workspace.'],
                    ['title' => 'Keep every hotel team organised', 'description' => 'Manage employees, seat limits, departments and reminders while each hotel stays securely separated.'],
                    ['title' => 'Turn learning activity into useful evidence', 'description' => 'See progress, scores and detailed answers, then export the data needed for follow-up and research.'],
                ],
            ],
            'ai' => [
                'eyebrow' => 'AI-assisted practice',
                'title' => 'Practise real conversations with helpful AI.',
                'description' => 'Give employees a safe place to try guest conversations and get encouraging feedback.',
                'review_note' => 'AI-generated content stays a draft for administrator review before it is published.',
                'items' => [
                    ['title' => 'Hotel guest role-play', 'description' => 'Practise realistic department scenarios by voice or text while the AI stays in its assigned guest role.'],
                    ['title' => 'Speaking and writing feedback', 'description' => 'Give adult learners encouraging, structured feedback and a clearer suggested response.'],
                    ['title' => 'AI-assisted content creation', 'description' => 'Draft explanations, examples, scenarios and assessment questions for an administrator to review.'],
                    ['title' => 'Normal and slow lesson audio', 'description' => 'Generate reusable audio files so learners can listen again without waiting for a live AI request.'],
                ],
            ],
            'pricing' => [
                'eyebrow' => 'Hotel subscriptions',
                'title' => 'Choose the space your team needs.',
                'description' => 'Every plan includes the complete learning platform. Choose by active employee capacity and included AI practice points.',
                'monthly_label' => 'per month',
                'employees_label' => 'active employees',
                'ai_points_label' => 'AI practice points included',
                'button_text' => 'Choose this plan',
                'featured_label' => 'Most popular',
                'inclusions' => [
                    'Department-based training',
                    'AI role-play and feedback',
                    'Assessments and certificates',
                    'Manager progress reporting',
                ],
                'footnote' => 'Your request stays pending until the GHASIDO team confirms the plan and activates your hotel.',
                'region_algeria' => 'Algeria (DZD)',
                'region_international' => 'International (USD)',
            ],
            'checkout' => [
                'eyebrow' => 'Complete your hotel request',
                'title' => 'Set up your hotel for GHASIDO.',
                'description' => 'Create the first manager account and send your selected plan for approval.',
                'summary_title' => 'Your selected plan',
                'form_title' => 'Hotel and manager details',
                'payment_title' => 'Payment and activation',
                'payment_description' => 'After review, the GHASIDO team confirms your payment method and activates the manager account.',
                'submit_button' => 'Send subscription request',
                'approval_note' => 'No access is activated until a Super Admin approves the hotel.',
                'back_to_plans' => 'Back to all plans',
            ],
            'call_to_action' => [
                'eyebrow' => 'Bring GHASIDO to your hotel',
                'title' => 'Help every team feel ready for the next guest.',
                'description' => 'Explore a practical English training platform made for hotel staff.',
                'button_text' => 'Get started',
            ],
            'footer' => [
                'tagline' => 'English training made for hotel teams.',
            ],
            'support' => [
                'whatsapp_number' => '',
                'phone' => '',
                'email' => '',
            ],
            'contact' => [
                'eyebrow' => 'Contact us',
                'title' => 'Let’s build the right plan for your team.',
                'description' => 'Tell us about your hotel and your team. We answer every message, usually within one working day.',
                'form_title' => 'Send us a message',
                'submit_button' => 'Send message',
                'success_message' => 'Thank you. Your message has been sent and we will get back to you soon.',
                'enterprise_title' => 'Hotel / Enterprise',
                'enterprise_subtitle' => 'More than 15 employees?',
                'enterprise_description' => 'We offer customised plans for hotels and large organisations. Get a tailored quote based on your team size, departments and training needs.',
                'enterprise_points' => [
                    'Flexible number of accounts',
                    'Customised training paths',
                    'Manager dashboard and detailed reports',
                    'Ongoing support',
                ],
                'enterprise_button' => 'Contact us',
                'enterprise_note' => 'Let’s build the right plan for your team.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        $content = LandingPageContent::query()->find(1)?->content;

        return $this->mergeWithDefaults(is_array($content) ? $content : []);
    }

    /** @param array<string, mixed> $content */
    public function save(array $content, User $actor): LandingPageContent
    {
        return DB::transaction(function () use ($content, $actor): LandingPageContent {
            $page = LandingPageContent::query()->find(1) ?? new LandingPageContent;
            $isNew = ! $page->exists;

            if ($isNew) {
                $page->id = 1;
            }

            $page->content = $this->mergeWithDefaults($content);
            $page->updated_by = $actor->id;

            if ($isNew) {
                $page->save();
                AuditLog::record($page, 'landing-page.content.updated', [
                    'sections' => array_keys($content),
                ]);
            } else {
                AuditLog::record($page, 'landing-page.content.updated');
                $page->save();
            }

            return $page;
        });
    }

    /** @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    private function mergeWithDefaults(array $content): array
    {
        $merged = array_replace_recursive($this->defaults(), $content);
        $roleItems = $merged['roles']['items'] ?? [];

        if (is_array($roleItems)) {
            // The public role overview is for hotel users. The platform owner
            // role is deliberately kept out of this marketing section.
            $merged['roles']['items'] = array_values(array_filter(
                $roleItems,
                static fn (mixed $item): bool => ! is_array($item)
                    || strcasecmp((string) ($item['title'] ?? ''), 'Super Admin') !== 0,
            ));
        }

        if (($merged['roles']['eyebrow'] ?? null) === 'One platform, four clear experiences') {
            $merged['roles']['eyebrow'] = 'One platform, three clear experiences';
        }

        return $merged;
    }
}
