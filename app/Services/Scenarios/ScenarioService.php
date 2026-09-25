<?php

namespace App\Services\Scenarios;

use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\ScenarioDifficulty;
use App\Jobs\GenerateScenarioDraft;
use App\Models\AiScenario;
use App\Models\AuditLog;
use App\Models\User;
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
