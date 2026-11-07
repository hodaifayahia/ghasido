<?php

namespace Tests\Feature\Console;

use App\Enums\ApiAccount;
use App\Models\ApiAccountSetting;
use App\Services\Owner\ApiKeyring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `php artisan ai:set-key` (user request 2026-11-07): the key is stored
 * encrypted like the owner console's form and takes over from .env.
 */
class SetApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_claude_key_is_stored_and_used()
    {
        config()->set('services.ai.key', 'sk-ant-from-env-0000000000');

        $this->artisan('ai:set-key', ['account' => 'anthropic'])
            ->expectsQuestion('Paste the Claude API key (it is not shown)', 'sk-ant-api03-new-key-1234567890abcd')
            ->expectsOutputToContain('key saved (ends with abcd)')
            ->assertSuccessful();

        $this->assertSame('sk-ant-api03-new-key-1234567890abcd', ApiAccountSetting::for(ApiAccount::Anthropic)->key());
        $this->assertSame('sk-ant-api03-new-key-1234567890abcd', app(ApiKeyring::class)->effective(ApiAccount::Anthropic));
        // Never stored in plain text.
        $this->assertDatabaseMissing('api_account_settings', ['api_key' => 'sk-ant-api03-new-key-1234567890abcd']);
    }

    public function test_a_blank_value_changes_nothing_and_clear_falls_back_to_env()
    {
        $this->artisan('ai:set-key', ['account' => 'anthropic'])
            ->expectsQuestion('Paste the Claude API key (it is not shown)', 'short')
            ->assertFailed();

        $this->artisan('ai:set-key', ['account' => 'anthropic', '--clear' => true])->assertSuccessful();
        $this->assertNull(ApiAccountSetting::for(ApiAccount::Anthropic)->key());
    }
}
