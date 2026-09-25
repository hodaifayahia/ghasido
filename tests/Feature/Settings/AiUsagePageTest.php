<?php

namespace Tests\Feature\Settings;

use App\Contracts\AiUsageInfo;
use App\Contracts\TtsProvider;
use App\Enums\AiFeature;
use App\Jobs\GenerateAudioClip;
use App\Models\AiModelPrice;
use App\Models\AiUsage;
use App\Models\AudioClip;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Ai\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Settings → AI usage (API-03, AIL-04; spec 0005 §4.3): costs from the
 * price table, old rows estimated, TTS metered, and the page a 403 for
 * everyone else. The prices themselves are the platform owner's (spec 0007,
 * D8): their editor is covered in Tests\Feature\Owner\OwnerConsoleTest.
 */
class AiUsagePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function usage(string $model, int $in, int $out, ?Hotel $hotel = null, AiFeature $feature = AiFeature::RoleplayTurn): AiUsage
    {
        $user = $hotel === null ? null : User::factory()->employee()->create(['hotel_id' => $hotel->id]);

        return app(UsageMeter::class)->record($user, $feature, new AiUsageInfo($in, $out, $model, 'test'), chargePoints: false);
    }

    public function test_a_priced_model_is_costed_when_the_row_is_written()
    {
        AiModelPrice::query()->create(['model' => 'priced-model', 'input_per_million' => 2, 'output_per_million' => 10]);

        $usage = $this->usage('priced-model', 1_000_000, 100_000);

        // 1M in × 2 + 0.1M out × 10 = 3.
        $this->assertSame('3.000000', $usage->cost_estimate);
    }

    public function test_the_page_sums_costs_and_estimates_rows_written_before_a_price_existed()
    {
        $hotel = Hotel::factory()->create(['name' => 'Hotel Atlas']);
        $this->usage('late-priced', 500_000, 0, $hotel);
        AiModelPrice::query()->create(['model' => 'late-priced', 'input_per_million' => 4, 'output_per_million' => 0]);
        $this->usage('late-priced', 500_000, 0, $hotel);
        $this->usage('never-priced', 10, 10, null, AiFeature::LearnerCoach);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('ai-usage.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/AiUsage')
                ->where('report.totals.calls', 3)
                ->where('report.totals.cost', 4) // 2 stored + 2 estimated
                ->where('report.totals.estimated', true)
                ->where('report.unpricedModels', ['never-priced'])
                ->where('report.byHotel.0.hotel', 'Hotel Atlas')
                ->has('report.daily', 30)
                ->etc());
    }

    public function test_the_hotel_filter_confines_the_figures()
    {
        $atlas = Hotel::factory()->create();
        $other = Hotel::factory()->create();
        $this->usage('m', 10, 10, $atlas);
        $this->usage('m', 10, 10, $other);
        $this->usage('m', 10, 10, $other);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('ai-usage.index', ['hotel' => $atlas->id, 'period' => 7]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.totals.calls', 1)
                ->where('report.period', 7)
                ->has('report.daily', 7)
                ->etc());
    }

    public function test_the_super_admin_cannot_change_the_prices()
    {
        AiModelPrice::query()->create(['model' => 'qwen3.8-flash', 'input_per_million' => 1, 'output_per_million' => 4]);

        // Whoever sets prices sets the dollar credit, so the price editor
        // is the owner's (spec 0007, D8): the web session does not pass.
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('owner.prices.update'), ['prices' => [
                ['model' => 'qwen3.8-flash', 'unit' => 'tokens', 'input' => 0, 'output' => 0],
            ]])
            ->assertRedirect(route('owner.login'));

        $this->assertSame('1.0000', AiModelPrice::query()->sole()->input_per_million);
        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'ai_price.%')->count());
    }

    public function test_nobody_but_the_super_admin_reaches_the_page()
    {
        $hotel = Hotel::factory()->create();

        foreach ([
            User::factory()->admin()->create(['hotel_id' => $hotel->id]),
            User::factory()->manager()->create(['hotel_id' => $hotel->id]),
            User::factory()->employee()->create(['hotel_id' => $hotel->id]),
        ] as $user) {
            $this->actingAs($user)->get(route('ai-usage.index'))->assertForbidden();
        }
    }

    public function test_speech_generation_is_metered_in_characters()
    {
        Storage::fake('public');
        $clip = AudioClip::factory()->create(['text' => 'Welcome to the hotel.']);

        (new GenerateAudioClip($clip->id))->handle(app(TtsProvider::class), app(UsageMeter::class));

        $usage = AiUsage::query()->where('feature', AiFeature::Tts->value)->sole();
        $this->assertSame(21, $usage->prompt_tokens);
        $this->assertSame($clip->voice, $usage->model);
    }
}
