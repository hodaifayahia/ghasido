<?php

namespace App\Services\Ai;

use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Enums\GenerationStatus;
use App\Enums\RoleplayStatus;
use App\Models\AiModelPrice;
use App\Models\AiUsage;
use App\Models\Attempt;
use App\Models\AudioClip;
use App\Models\RoleplayAttempt;
use App\Services\Stt\DeepgramSpeechToTextProvider;
use App\Services\Stt\FakeSpeechToTextProvider;
use App\Services\VoiceAgent\VoiceCallService;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;

/**
 * Puts right the ai_usages ledger written before every call was metered
 * under the account that billed it (API-03, AIL-04; spec 0007).
 *
 * Until 2026-09-29 lesson audio and spoken-answer transcriptions were
 * labelled with the `.env` provider, which is `fake` when the Super Admin
 * switched them to real on Settings → AI models: those rows never reached
 * the Deepgram credit. Live calls whose page went away were never closed,
 * so never metered. Every step is idempotent: a row or call it has fixed no
 * longer matches, so a second run changes nothing.
 */
final class UsageLedgerRepair
{
    /** A real clip's usage row is written seconds before the clip is done. */
    private const int CLIP_WINDOW_MINUTES = 10;

    public function __construct(
        private readonly AiModelSettings $settings,
        private readonly VoiceCallService $calls,
    ) {}

    /**
     * Relabel the `fake` TTS rows written for audio a real provider made.
     * The fake provider only ever makes WAV tones, so a generated clip in
     * any other format was real speech.
     *
     * @return int rows relabelled (or that would be, on a dry run)
     */
    public function relabelSpeech(bool $dryRun = false): int
    {
        $provider = $this->realProvider('tts', 'deepgram');
        $fixed = 0;
        /** @var list<int> $claimed */
        $claimed = [];

        AudioClip::query()
            ->with('mediaAsset')
            ->where('status', GenerationStatus::Done->value)
            ->whereNotNull('generated_at')
            ->where(fn ($query) => $query->whereNull('provider')->orWhere('provider', '<>', 'upload'))
            ->whereHas('mediaAsset', fn ($query) => $query->where('mime', '<>', 'audio/wav'))
            ->orderBy('id')
            ->chunkById(500, function ($clips) use ($provider, $dryRun, &$fixed, &$claimed): void {
                foreach ($clips as $clip) {
                    /** @var AudioClip $clip */
                    $generatedAt = $clip->generated_at;

                    if ($generatedAt === null) {
                        continue;
                    }

                    $row = AiUsage::query()
                        ->forFeature(AiFeature::Tts)
                        ->where('provider', 'fake')
                        ->where('model', $clip->voice)
                        ->where('prompt_tokens', mb_strlen($clip->text))
                        ->whereBetween('occurred_at', [$generatedAt->copy()->subMinutes(self::CLIP_WINDOW_MINUTES), $generatedAt->copy()->addMinute()])
                        ->whereNotIn('id', $claimed)
                        ->orderByDesc('occurred_at')
                        ->first();

                    if ($row === null) {
                        continue;
                    }

                    $fixed++;
                    $claimed[] = $row->id;

                    if (! $dryRun) {
                        $this->relabel($row, $provider, $row->model ?? $clip->voice);
                    }
                }
            });

        return $fixed;
    }

    /**
     * Relabel the `fake` transcription rows of spoken answers a real
     * provider transcribed (its transcript is not the fake one), matched by
     * learner and recording length.
     *
     * @return int rows relabelled (or that would be, on a dry run)
     */
    public function relabelTranscriptions(bool $dryRun = false): int
    {
        $provider = $this->realProvider('stt', DeepgramSpeechToTextProvider::PROVIDER);
        $model = $this->settings->sttModel($provider);
        $model = $model !== '' ? $model : $provider;
        $fixed = 0;

        AiUsage::query()
            ->forFeature(AiFeature::Stt)
            ->where('provider', 'fake')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($provider, $model, $dryRun, &$fixed): void {
                foreach ($rows as $row) {
                    /** @var AiUsage $row */
                    $real = Attempt::query()
                        ->where('user_id', $row->user_id)
                        ->whereNotNull('response_media_id')
                        ->whereNotNull('transcript')
                        ->where('transcript', '<>', FakeSpeechToTextProvider::TRANSCRIPT)
                        ->where('created_at', '<=', $row->occurred_at)
                        ->where('updated_at', '>=', $row->occurred_at)
                        ->exists();

                    if (! $real) {
                        continue;
                    }

                    $fixed++;

                    if (! $dryRun) {
                        $this->relabel($row, $provider, $model);
                    }
                }
            });

        return $fixed;
    }

    /**
     * A fake call is free: clear any cost a `fake` row was given from its
     * model id's price before fake rows were excluded from pricing.
     *
     * @return int rows cleared
     */
    public function clearFakeCosts(bool $dryRun = false): int
    {
        $query = AiUsage::query()->where('provider', 'fake')->where('cost_estimate', '>', 0);

        return $dryRun ? $query->count() : $query->update(['cost_estimate' => 0]);
    }

    /**
     * Close the live voice calls left open, so their length is metered.
     *
     * @return int calls closed (or that would be, on a dry run)
     */
    public function closeStaleVoiceCalls(bool $dryRun = false): int
    {
        if ($dryRun) {
            return RoleplayAttempt::query()
                ->where('channel', RoleplayAttempt::CHANNEL_VOICE_CALL)
                ->where('status', RoleplayStatus::InProgress->value)
                ->where('updated_at', '<', Date::now()->subMinutes(VoiceCallService::STALE_AFTER_MINUTES))
                ->count();
        }

        return $this->calls->closeStale();
    }

    /**
     * Stamp today's price on real rows stored at $0 that have a price now,
     * so their cost stops moving when the owner edits a price later.
     *
     * @return int rows priced (or that would be, on a dry run)
     */
    public function reprice(bool $dryRun = false): int
    {
        $prices = AiModelPrice::byModel();
        $fixed = 0;

        AiUsage::query()
            ->where('provider', '<>', 'fake')
            ->where('cost_estimate', '<=', 0)
            ->where(fn ($query) => $query->where('prompt_tokens', '>', 0)->orWhere('completion_tokens', '>', 0))
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($prices, $dryRun, &$fixed): void {
                foreach ($rows as $row) {
                    /** @var AiUsage $row */
                    $cost = AiModelPrice::lookup($prices, (string) $row->model)?->costOf($row->prompt_tokens, $row->completion_tokens) ?? 0.0;

                    if ($cost <= 0) {
                        continue;
                    }

                    $fixed++;

                    if (! $dryRun) {
                        AiUsage::query()->whereKey($row->id)->where('cost_estimate', '<=', 0)->update(['cost_estimate' => $cost]);
                    }
                }
            });

        return $fixed;
    }

    /**
     * The ledger since a date, per provider, feature and model, with the
     * account each bills to, for the command's report.
     *
     * @return list<array{provider: string, account: string, feature: string, model: string, calls: int, units: int, cost: float, points: int, priced: bool}>
     */
    public function summary(DateTimeInterface $since): array
    {
        $prices = AiModelPrice::byModel();
        $rows = AiUsage::query()
            ->where('occurred_at', '>=', $since)
            ->groupBy('provider', 'feature', 'model')
            ->toBase()
            ->selectRaw('provider, feature, model, count(*) as calls, sum(prompt_tokens + completion_tokens) as units, sum(cost_estimate) as cost, sum(points_charged) as points')
            ->orderBy('provider')
            ->orderBy('feature')
            ->get();

        $summary = [];

        foreach ($rows as $row) {
            $provider = (string) $row->provider;
            $summary[] = [
                'provider' => $provider,
                'account' => ApiAccount::forProvider($provider)->value ?? '—',
                'feature' => (string) $row->feature,
                'model' => (string) $row->model,
                'calls' => (int) $row->calls,
                'units' => (int) $row->units,
                'cost' => round((float) $row->cost, 6),
                'points' => (int) $row->points,
                'priced' => $provider === 'fake' || AiModelPrice::lookup($prices, (string) $row->model) !== null,
            ];
        }

        return $summary;
    }

    /**
     * The real provider a capability runs on now, or the given one while
     * it is switched to fake (the audio was made while it was real).
     */
    private function realProvider(string $capability, string $fallback): string
    {
        $provider = $this->settings->currentProvider($capability);

        return $provider !== 'fake' ? $provider : $fallback;
    }

    /**
     * Only a row still labelled `fake` is changed, so a second run (or a
     * concurrent one) cannot relabel it twice. Its cost is recomputed at
     * today's price for its model.
     */
    private function relabel(AiUsage $row, string $provider, string $model): void
    {
        $cost = AiModelPrice::for($model)?->costOf($row->prompt_tokens, $row->completion_tokens) ?? 0.0;

        AiUsage::query()
            ->whereKey($row->id)
            ->where('provider', 'fake')
            ->update(['provider' => $provider, 'model' => $model, 'cost_estimate' => $cost]);
    }
}
