<?php

namespace App\Console\Commands;

use App\Models\AiScenario;
use App\Models\RoleplayAttempt;
use App\Services\VoiceAgent\VoiceAgentSessionFactory;
use App\Services\VoiceAgent\VoiceAgentSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live end-to-end check of the Deepgram Voice Agent (spec 0004), used by
 * storage/harness/voice-agent-probe.mjs.
 *
 * Builds a real session exactly as a learner's call would (short-lived token,
 * server-assembled Settings, RP-04) and synthesises one spoken employee line
 * as raw linear16 at the agent's input rate. Nothing is saved: the attempt is
 * an unsaved model, and the scenario is a published one or an unsaved sample.
 * Writes JSON to --out; the Deepgram key itself is never printed (SEC-03).
 */
#[Signature('voice-agent:probe-session
    {--scenario= : ai_scenarios id (default: first published, else an unsaved sample)}
    {--say=Good evening, welcome to our hotel. Do you have a reservation? : what the employee says}
    {--out=/tmp/voice-agent-probe : directory for session.json and user.pcm}
    {--skip-grant : if the token grant is refused, still build the Settings so the agent can be tested with the server key}')]
#[Description('Build a real voice-agent session + a spoken test line for the live probe')]
final class VoiceAgentProbeSession extends Command
{
    public function handle(VoiceAgentSessionFactory $factory, VoiceAgentSettings $settings): int
    {
        $scenario = $this->scenario();
        $attempt = new RoleplayAttempt;
        $attempt->ai_scenario_id = $scenario->id;

        $out = rtrim((string) $this->option('out'), '/');
        if (! is_dir($out) && ! mkdir($out, 0775, true)) {
            $this->error("Cannot create {$out}");

            return self::FAILURE;
        }

        try {
            $started = microtime(true);
            $session = $factory->create($attempt, $scenario);
            $grantMs = (int) round((microtime(true) - $started) * 1000);
        } catch (Throwable $e) {
            if (! $this->option('skip-grant')) {
                $this->error('Token grant / session failed: '.$e->getMessage());
                $this->line('Re-run with --skip-grant to test the agent itself with the server key (DG_KEY env in the probe).');

                return self::FAILURE;
            }

            // Server-side only: the probe authenticates with the key from its
            // own environment; no token and no key is written to disk.
            $this->warn('Token grant failed ('.$e->getMessage().'); continuing with --skip-grant.');
            $values = $settings->forScenario($scenario);
            $session = [
                'url' => (string) config('services.voice_agent.url'),
                'token' => '',
                'expiresIn' => 0,
                'settings' => $factory->settingsMessage($attempt, $scenario, $values),
                'inputSampleRate' => (int) $values['inputSampleRate'],
                'outputSampleRate' => (int) $values['outputSampleRate'],
                'thinkMode' => (string) $values['thinkMode'],
            ];
            $grantMs = -1;
        }

        $rate = (int) $session['inputSampleRate'];
        $say = (string) $this->option('say');

        try {
            $pcm = $this->speak($say, $rate);
        } catch (Throwable $e) {
            $this->error('Could not synthesise the test line: '.$e->getMessage());

            return self::FAILURE;
        }

        file_put_contents($out.'/user.pcm', $pcm);
        file_put_contents($out.'/session.json', json_encode([
            'url' => $session['url'],
            'token' => $session['token'],
            'settings' => $session['settings'],
            'inputSampleRate' => $rate,
            'outputSampleRate' => (int) $session['outputSampleRate'],
            'say' => $say,
            'scenario' => $scenario->title,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->line(sprintf(
            'session ok: grant %d ms, token ttl %ds, think %s, speak %s, listen %s; test line %.1fs of audio',
            $grantMs,
            $session['expiresIn'],
            $session['thinkMode'],
            json_encode(data_get($session['settings'], 'agent.speak.provider')),
            (string) data_get($session['settings'], 'agent.listen.provider.model'),
            strlen($pcm) / 2 / $rate,
        ));

        return self::SUCCESS;
    }

    private function scenario(): AiScenario
    {
        $id = $this->option('scenario');
        if (is_numeric($id)) {
            return AiScenario::query()->findOrFail((int) $id);
        }

        return AiScenario::query()->where('status', 'published')->first()
            ?? AiScenario::factory()->make([
                'title' => 'Late check-in without a reservation',
                'situation' => 'A tired guest arrives at the front desk at 11 pm and has no booking.',
                'ai_role' => 'Hotel guest who needs a room for one night',
                'employee_role' => 'Receptionist',
                'objective' => 'Find the guest a room, confirm the price and ask for ID.',
            ]);
    }

    /** One line of employee speech as raw linear16 mono at $rate Hz. */
    private function speak(string $text, int $rate): string
    {
        $key = (string) config('services.tts.key');
        if ($key === '') {
            throw new \RuntimeException('No Deepgram key (TTS_KEY / DEEPGRAM_API_KEY).');
        }

        $response = Http::withHeaders(['Authorization' => 'Token '.$key])
            ->connectTimeout(20)
            ->timeout(90)
            ->asJson()
            ->post('https://api.deepgram.com/v1/speak?'.http_build_query([
                'model' => 'aura-2-apollo-en',
                'encoding' => 'linear16',
                'sample_rate' => $rate,
                'container' => 'none',
            ]), ['text' => $text]);

        if ($response->failed() || $response->body() === '') {
            throw new \RuntimeException(sprintf('HTTP %d %s', $response->status(), mb_substr($response->body(), 0, 200)));
        }

        return $response->body();
    }
}
