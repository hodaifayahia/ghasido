<?php

namespace App\Services\Scenarios;

use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\ScenarioDifficulty;
use App\Jobs\GenerateScenarioDraft;
use App\Models\AiScenario;
use App\Models\AuditLog;
use App\Models\RoleplayAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes the scenario aggregate and keeps AI output reviewable (RP-01..06,
 * GEN-01, GEN-03, GEN-04). A generated response is never copied into live
 * scenario fields until the author explicitly applies it.
 */
final class ScenarioService
{
    /**
     * @param  array{title: string, department_id: int, difficulty: ScenarioDifficulty}  $data
     */
    public function create(array $data, User $actor): AiScenario
    {
        return DB::transaction(function () use ($data, $actor): AiScenario {
            $title = trim($data['title']);
            $scenario = new AiScenario;
            $scenario->fill([
                'department_id' => $data['department_id'],
                'hotel_id' => null,
                'title' => $title,
                'slug' => $this->uniqueSlug($title),
                'description' => '',
                'difficulty' => $data['difficulty'],
                'situation' => '',
                'ai_role' => '',
                'employee_role' => '',
                'objective' => '',
                'goals' => [],
                'useful_phrases' => [],
                'feedback_criteria' => AiScenario::defaultFeedbackCriteria(),
                'attempts_allowed' => 3,
                'input_mode' => 'both',
                'min_turns' => 4,
                'max_turns' => 12,
                'icon' => 'bell',
                'status' => ContentStatus::Draft,
                'created_by' => $actor->id,
            ]);
            $scenario->save();

            AuditLog::record($scenario, 'scenario.created', [
                'created' => $scenario->only(['title', 'department_id', 'difficulty']),
            ]);

            return $scenario;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiScenario $scenario, array $data): AiScenario
    {
        return DB::transaction(function () use ($scenario, $data): AiScenario {
            $scenario->fill($data);
            AuditLog::record($scenario, 'scenario.updated');
            $scenario->save();

            return $scenario->refresh();
        });
    }

    public function generate(AiScenario $scenario): AiScenario
    {
        return DB::transaction(function () use ($scenario): AiScenario {
            $scenario->forceFill([
                'ai_draft' => null,
                'ai_status' => GenerationStatus::Pending,
            ])->save();
            AuditLog::record($scenario, 'scenario.generate_requested');

            GenerateScenarioDraft::dispatch($scenario->id);

            return $scenario->refresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $edited
     */
    public function applyDraft(AiScenario $scenario, ?array $edited = null): AiScenario
    {
        return DB::transaction(function () use ($scenario, $edited): AiScenario {
            $draft = $scenario->ai_draft ?? [];
            $values = array_intersect_key(
                array_merge($draft, $edited ?? []),
                array_flip([
                    'description',
                    'situation',
                    'ai_role',
                    'employee_role',
                    'objective',
                    'goals',
                    'useful_phrases',
                    'quote',
                    'tip',
                ]),
            );

            $scenario->fill($values);
            $scenario->forceFill([
                'ai_draft' => null,
                'ai_status' => null,
                'status' => ContentStatus::Draft,
            ]);
            AuditLog::record($scenario, 'scenario.draft_applied');
            $scenario->save();

            return $scenario->refresh();
        });
    }

    public function publish(AiScenario $scenario): AiScenario
    {
        return DB::transaction(function () use ($scenario): AiScenario {
            $scenario->status = ContentStatus::Published;
            AuditLog::record($scenario, 'scenario.published');
            $scenario->save();

            return $scenario->refresh();
        });
    }

    /**
     * Role-play attempts learners made on each scenario. Admin previews
     * (RP-13) are not learner data and do not count.
     *
     * @param  list<int>  $scenarioIds
     * @return array<int, int> scenario id => learner attempts
     */
    public function learnerAttemptCounts(array $scenarioIds): array
    {
        $counts = array_fill_keys($scenarioIds, 0);

        if ($scenarioIds === []) {
            return $counts;
        }

        $rows = RoleplayAttempt::query()
            ->whereIn('ai_scenario_id', $scenarioIds)
            ->where('is_preview', false)
            ->selectRaw('ai_scenario_id, count(*) as total')
            ->groupBy('ai_scenario_id')
            ->get();

        foreach ($rows as $row) {
            $counts[(int) $row->getAttribute('ai_scenario_id')] = (int) $row->getAttribute('total');
        }

        return $counts;
    }

    /**
     * "Delete" from the scenario table (CMS-01, RP-01).
     *
     * The scenario always leaves every lesson step that offered it. With no
     * learner attempts it is deleted, with the admin's own preview runs.
     * With learner attempts nothing that holds a transcript or a score is
     * deleted (RP-11, DATA-05, DATA-10): the scenario becomes a draft marked
     * archived, leaves the library and every learner, and its attempts keep
     * pointing at it for reports.
     *
     * @return bool true when the scenario row was deleted, false when removed
     */
    public function delete(AiScenario $scenario): bool
    {
        return DB::transaction(function () use ($scenario): bool {
            $attempts = $this->learnerAttemptCounts([$scenario->id])[$scenario->id] ?? 0;
            $blockIds = $scenario->blocks()->pluck('blocks.id')->all();
            $scenario->blocks()->detach();

            if ($attempts > 0) {
                $scenario->status = ContentStatus::Draft;
                $scenario->archived_at = Carbon::now();
                AuditLog::record($scenario, 'scenario.removed', [
                    'learner_attempts' => $attempts,
                    'detached_block_ids' => $blockIds,
                    'kept_for_reports' => true,
                ]);
                $scenario->save();

                return false;
            }

            AuditLog::record($scenario, 'scenario.deleted', [
                'deleted' => $scenario->only(['id', 'title', 'department_id', 'hotel_id', 'difficulty']),
                'detached_block_ids' => $blockIds,
            ]);

            RoleplayAttempt::query()
                ->where('ai_scenario_id', $scenario->id)
                ->where('is_preview', true)
                ->delete();
            $scenario->delete();

            return true;
        });
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'scenario';
        $slug = $base;
        $suffix = 2;

        while (AiScenario::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
