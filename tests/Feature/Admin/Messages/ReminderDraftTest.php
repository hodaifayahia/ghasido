<?php

namespace Tests\Feature\Admin\Messages;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Models\AiUsage;
use App\Models\Hotel;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\Ai\FakeAiProvider;
use App\Services\Ai\OpenAiCompatibleAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Draft with AI" for reminder templates (spec 0005 §4.2, GEN-03): only the
 * template author may ask, the job writes a draft with the platform's
 * placeholders only, and nothing is saved as a template by itself.
 */
class ReminderDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_super_admin_gets_an_editable_draft_and_nothing_is_saved()
    {
        $this->app->instance(AiProvider::class, new FakeAiProvider);
        $admin = User::factory()->superAdmin()->create();

        // Sync queue: the job runs inside the request.
        $this->actingAs($admin)
            ->postJson(route('messages-reminders.templates.draft'), ['purpose' => 'Learners who stopped practising', 'tone' => 'friendly'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('subject', 'A quick English practice today');

        $this->actingAs($admin)
            ->getJson(route('messages-reminders.templates.draft.show'))
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('status', 'done')->where('body', fn (string $body): bool => str_contains($body, '{{name}}'))->etc());

        $this->assertSame(0, ReminderTemplate::query()->count());

        $usage = AiUsage::query()->where('feature', AiFeature::ReminderDraft->value)->sole();
        $this->assertSame($admin->id, $usage->user_id);
        $this->assertSame(0, (int) $usage->points_charged);
    }

    public function test_a_manager_or_hotel_admin_cannot_ask_for_a_draft()
    {
        $hotel = Hotel::factory()->create();

        foreach ([User::factory()->manager()->create(['hotel_id' => $hotel->id]), User::factory()->admin()->create(['hotel_id' => $hotel->id])] as $user) {
            $this->actingAs($user)
                ->postJson(route('messages-reminders.templates.draft'), ['purpose' => 'Learners who stopped practising', 'tone' => 'friendly'])
                ->assertForbidden();
        }

        $this->assertSame(0, AiUsage::query()->count());
    }

    public function test_the_request_is_validated()
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->postJson(route('messages-reminders.templates.draft'), ['purpose' => 'Hi', 'tone' => 'angry'])
            ->assertJsonValidationErrors(['purpose', 'tone']);
    }

    public function test_a_placeholder_the_platform_does_not_know_is_removed()
    {
        Http::fake(['*' => Http::response([
            'model' => 'm',
            'choices' => [['message' => ['content' => json_encode([
                'subject' => 'Hello {{name}}',
                'body' => 'Hi {{name}}, your manager {{manager_phone}} says: continue at {{login_url}}.',
            ])]]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
        ])]);

        $draft = (new OpenAiCompatibleAiProvider(apiKey: 'k', model: 'm', baseUrl: 'https://llm.example.test/v1'))
            ->draftReminder('Inactive learners', 'friendly', ReminderTemplate::VARIABLES);

        $this->assertSame('Hello {{name}}', $draft->subject);
        $this->assertSame('Hi {{name}}, your manager  says: continue at {{login_url}}.', $draft->body);
    }
}
