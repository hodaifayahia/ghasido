<?php

namespace App\Services\Ai;

use App\Enums\AiFeature;
use App\Models\AiModelPrice;
use App\Models\AiUsage;
use App\Models\Hotel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * Settings → AI usage (API-03, AIL-04; spec 0005 §4.3): what the platform's
 * AI calls cost, by feature, model, hotel and day, for the Super Admin.
 *
 * Summed with a handful of grouped queries, never per row. A row written
 * before its model had a price (or before prices existed) is costed at the
 * current price and flagged as an estimate, so old usage is not shown as
 * free. A model with no price is listed so the admin can add one.
 */
final class AiUsageReport
{
    /** @var list<int> */
    public const PERIODS = [7, 30, 90];

    /**
     * @return array<string, mixed>
     */
    public function build(int $days, ?int $hotelId): array
    {
        $from = CarbonImmutable::instance(Date::now())->startOfDay()->subDays($days - 1);
        $prices = AiModelPrice::byModel();

        $base = fn (): Builder => AiUsage::query()
            ->where('occurred_at', '>=', $from)
            ->when($hotelId !== null, fn (Builder $query) => $query->where('hotel_id', $hotelId));

        $byModel = [];
        $totals = ['calls' => 0, 'promptTokens' => 0, 'completionTokens' => 0, 'cost' => 0.0, 'points' => 0, 'estimated' => false];

        $rows = $base()
            ->groupBy('provider', 'model')
            ->toBase()
            ->selectRaw('provider, model, count(*) as calls, sum(prompt_tokens) as prompt_tokens, sum(completion_tokens) as completion_tokens, sum(cost_estimate) as cost, sum(points_charged) as points, sum(case when cost_estimate > 0 then 0 else 1 end) as unpriced_calls, sum(case when cost_estimate > 0 then 0 else prompt_tokens end) as unpriced_prompt, sum(case when cost_estimate > 0 then 0 else completion_tokens end) as unpriced_completion')
            ->get();

        foreach ($rows as $row) {
            $model = (string) $row->model;
            $price = AiModelPrice::lookup($prices, $model);
            $stored = (float) $row->cost;
            $estimate = $price?->costOf((int) $row->unpriced_prompt, (int) $row->unpriced_completion) ?? 0.0;

            $byModel[] = [
                'provider' => (string) $row->provider,
                'model' => $model,
                'calls' => (int) $row->calls,
                'promptTokens' => (int) $row->prompt_tokens,
                'completionTokens' => (int) $row->completion_tokens,
                'cost' => round($stored + $estimate, 4),
                'priced' => $price !== null,
                'unit' => $price->unit ?? null,
                'estimated' => $estimate > 0,
            ];

            $totals['calls'] += (int) $row->calls;
            $totals['promptTokens'] += (int) $row->prompt_tokens;
            $totals['completionTokens'] += (int) $row->completion_tokens;
            $totals['cost'] += $stored + $estimate;
            $totals['points'] += (int) $row->points;
            $totals['estimated'] = $totals['estimated'] || $estimate > 0;
        }

        usort($byModel, fn (array $a, array $b): int => [$b['cost'], $b['calls']] <=> [$a['cost'], $a['calls']]);
        $totals['cost'] = round($totals['cost'], 4);

        return [
            'period' => $days,
            'hotel' => $hotelId,
            'totals' => $totals,
            'byModel' => $byModel,
            'byFeature' => $this->byFeature($base(), $prices),
            'byHotel' => $hotelId === null ? $this->byHotel($base(), $prices) : [],
            'daily' => $this->daily($base(), $prices, $from, $days),
            'unpricedModels' => array_values(array_map(
                fn (array $row): string => $row['model'],
                array_filter($byModel, fn (array $row): bool => ! $row['priced']),
            )),
        ];
    }

    /**
     * @param  Builder<AiUsage>  $query
     * @param  array<string, AiModelPrice>  $prices
     * @return list<array{feature: string, label: string, calls: int, cost: float}>
     */
    private function byFeature(Builder $query, array $prices): array
    {
        $totals = [];

        foreach ($this->costedGroups($query, 'feature', $prices) as $key => $group) {
            $feature = AiFeature::tryFrom($key);
            $totals[] = [
                'feature' => $key,
                'label' => $feature === null ? $key : self::featureLabel($feature),
                'calls' => $group['calls'],
                'cost' => $group['cost'],
            ];
        }

        usort($totals, fn (array $a, array $b): int => [$b['cost'], $b['calls']] <=> [$a['cost'], $a['calls']]);

        return $totals;
    }

    /**
     * @param  Builder<AiUsage>  $query
     * @param  array<string, AiModelPrice>  $prices
     * @return list<array{hotelId: int|null, hotel: string, calls: int, cost: float}>
     */
    private function byHotel(Builder $query, array $prices): array
    {
        $groups = $this->costedGroups($query, 'hotel_id', $prices);
        $names = Hotel::withoutGlobalScopes()->whereIn('id', array_filter(array_keys($groups), 'is_numeric'))->pluck('name', 'id');
        $totals = [];

        foreach ($groups as $key => $group) {
            $id = $key === '' ? null : (int) $key;
            $totals[] = [
                'hotelId' => $id,
                'hotel' => $id === null ? __('Platform (no hotel)') : (string) ($names[$id] ?? __('Hotel #:id', ['id' => $id])),
                'calls' => $group['calls'],
                'cost' => $group['cost'],
            ];
        }

        usort($totals, fn (array $a, array $b): int => [$b['cost'], $b['calls']] <=> [$a['cost'], $a['calls']]);

        return $totals;
    }

    /**
     * One entry per day of the period, oldest first, zero-filled.
     *
     * @param  Builder<AiUsage>  $query
     * @param  array<string, AiModelPrice>  $prices
     * @return list<array{date: string, label: string, calls: int, cost: float}>
     */
    private function daily(Builder $query, array $prices, CarbonImmutable $from, int $days): array
    {
        $byDay = [];

        // Bucketed in PHP so the same code runs on SQLite and MySQL.
        foreach ($query->get(['occurred_at', 'model', 'prompt_tokens', 'completion_tokens', 'cost_estimate']) as $usage) {
            $day = $usage->occurred_at->toDateString();
            $byDay[$day]['calls'] = ($byDay[$day]['calls'] ?? 0) + 1;
            $byDay[$day]['cost'] = ($byDay[$day]['cost'] ?? 0.0) + self::costOfRow($usage, $prices);
        }

        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $day = $from->addDays($offset);
            $key = $day->toDateString();
            $series[] = [
                'date' => $key,
                'label' => $day->format('j M'),
                'calls' => $byDay[$key]['calls'] ?? 0,
                'cost' => round($byDay[$key]['cost'] ?? 0.0, 4),
            ];
        }

        return $series;
    }

    /**
     * Calls and cost per value of one column, costing unpriced rows at the
     * current price.
     *
     * @param  Builder<AiUsage>  $query
     * @param  array<string, AiModelPrice>  $prices
     * @return array<string, array{calls: int, cost: float}>
     */
    private function costedGroups(Builder $query, string $column, array $prices): array
    {
        // A fixed SQL string per grouping column: nothing from the request
        // ever reaches selectRaw(). The alias is `group_key` because
        // `grouping` is a reserved word in MySQL 8 (SQLite accepts it).
        $select = $column === 'feature'
            ? 'feature as group_key, model, count(*) as calls, sum(cost_estimate) as cost, sum(case when cost_estimate > 0 then 0 else prompt_tokens end) as unpriced_prompt, sum(case when cost_estimate > 0 then 0 else completion_tokens end) as unpriced_completion'
            : 'hotel_id as group_key, model, count(*) as calls, sum(cost_estimate) as cost, sum(case when cost_estimate > 0 then 0 else prompt_tokens end) as unpriced_prompt, sum(case when cost_estimate > 0 then 0 else completion_tokens end) as unpriced_completion';

        $rows = $query
            ->groupBy($column === 'feature' ? 'feature' : 'hotel_id', 'model')
            ->toBase()
            ->selectRaw($select)
            ->get();

        $groups = [];

        foreach ($rows as $row) {
            $key = $row->group_key === null ? '' : (string) $row->group_key;
            $estimate = AiModelPrice::lookup($prices, (string) $row->model)?->costOf((int) $row->unpriced_prompt, (int) $row->unpriced_completion) ?? 0.0;
            $groups[$key]['calls'] = ($groups[$key]['calls'] ?? 0) + (int) $row->calls;
            $groups[$key]['cost'] = round(($groups[$key]['cost'] ?? 0.0) + (float) $row->cost + $estimate, 4);
        }

        return $groups;
    }

    /**
     * @param  array<string, AiModelPrice>  $prices
     */
    private static function costOfRow(AiUsage $usage, array $prices): float
    {
        $stored = (float) $usage->cost_estimate;

        if ($stored > 0) {
            return $stored;
        }

        return AiModelPrice::lookup($prices, (string) $usage->model)?->costOf($usage->prompt_tokens, $usage->completion_tokens) ?? 0.0;
    }

    public static function featureLabel(AiFeature $feature): string
    {
        return match ($feature) {
            AiFeature::RoleplayTurn => __('Role-play replies'),
            AiFeature::RoleplayEval => __('Role-play feedback'),
            AiFeature::LexiconGenerate => __('Vocabulary drafts'),
            AiFeature::ScenarioGenerate => __('Scenario drafts'),
            AiFeature::LessonGenerate => __('Lesson generation'),
            AiFeature::CourseOutline => __('Course outlines'),
            AiFeature::ImageGenerate => __('Images'),
            AiFeature::WritingEval => __('Writing evaluation'),
            AiFeature::SpeakingEval => __('Speaking evaluation'),
            AiFeature::TestQuestionsGenerate => __('Test questions'),
            AiFeature::Tts => __('Speech (text to speech)'),
            AiFeature::Stt => __('Transcription'),
            AiFeature::ProviderCheck => __('Connection checks'),
            AiFeature::LearnerCoach => __('Learner coaching'),
            AiFeature::DashboardBriefing => __('Dashboard briefings'),
            AiFeature::ReminderDraft => __('Reminder drafts'),
            AiFeature::PronunciationCheck => __('Pronunciation checks'),
            AiFeature::PronunciationGuide => __('Pronunciation guides'),
            AiFeature::PronunciationCoach => __('Pronunciation coaching'),
            AiFeature::VoiceCall => __('Live voice calls'),
        };
    }
}
