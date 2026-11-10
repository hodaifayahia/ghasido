<?php

namespace Tests\Unit;

use App\Services\Ai\AiErrorExplainer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Plain words for AI provider failures (client report 2026-11-07).
 */
class AiErrorExplainerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_anthropic_balance_says_where_to_add_credit()
    {
        $message = AiErrorExplainer::explain(new RuntimeException('HTTP request returned status code 400: {"type":"error","error":{"message":"Your credit balance is too low to access the Anthropic API."}}'), 'x');

        $this->assertStringContainsString('console.anthropic.com', $message);
    }

    public function test_a_denied_image_model_points_at_the_alibaba_console()
    {
        $message = AiErrorExplainer::explain(new RuntimeException('The image provider answered HTTP 403: Access to model denied.'), 'x');

        $this->assertStringContainsString('Alibaba', $message);
    }

    public function test_an_unknown_error_keeps_its_own_message()
    {
        $this->assertSame('Something odd', AiErrorExplainer::explain(new RuntimeException('Something odd'), 'x'));
        $this->assertSame('fallback', AiErrorExplainer::explain(null, 'fallback'));
    }

    // Alibaba refusals (client report 2026-11-10).
    public function test_an_unpaid_alibaba_account_is_explained()
    {
        $text = AiErrorExplainer::explain(new RuntimeException('HTTP request returned status code 403: {"code":"Arrearage","message":"Access denied, please make sure your account is in good standing."}'), 'fallback');

        $this->assertStringContainsString('unpaid balance', $text);
    }

    public function test_a_model_not_activated_is_explained()
    {
        $text = AiErrorExplainer::explain(new RuntimeException('HTTP request returned status code 403: {"code":"AccessDenied.Unpurchased","message":"Access to model denied."}'), 'fallback');

        $this->assertStringContainsString('not activated', $text);
    }

    public function test_any_other_alibaba_access_denied_gets_the_checklist()
    {
        $text = AiErrorExplainer::explain(new RuntimeException('HTTP request returned status code 403: {"code":"AccessDenied","message":"Forbidden."}'), 'fallback');

        $this->assertStringContainsString('refused the request (403)', $text);
    }
}
