<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\FillLessonWithAiRequest;
use App\Http\Requests\Admin\Lessons\GenerateImageRequest;
use App\Http\Requests\Admin\Lessons\StoreLessonGenerationRequest;
use App\Models\ContentGeneration;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Content\ContentTree;
use App\Services\Content\LessonGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Generate with AI" on Lessons & Content (GEN-01, GEN-03, GEN-04, PERF-04,
 * AIL-01..03; spec 0004). JSON endpoints: the dialog posts the request and
 * polls the generation row until it is done or failed.
 *
 * Read → authorize → delegate. LessonGenerator writes; the jobs call the AI.
 */
class LessonGenerationController extends Controller
{
    public function store(StoreLessonGenerationRequest $request, LessonGenerator $generator): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $generation = $generator->start($user, $request->generationData());
        } catch (AiLimitReached $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        return response()->json(['generation' => $generator->present($generation->refresh())], 202);
    }

    /**
     * "Fill this lesson with AI" (client request 2026-11-07): write one
     * existing lesson, on its own title, unit and course, from a brief.
     */
    public function fill(FillLessonWithAiRequest $request, Lesson $lesson, LessonGenerator $generator): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('web');

        try {
            $generation = $generator->startForLesson($user, $lesson, $request->generationData($lesson));
        } catch (AiLimitReached $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        return response()->json(['generation' => $generator->present($generation->refresh())], 202);
    }

    public function show(ContentGeneration $generation, LessonGenerator $generator): JsonResponse
    {
        Gate::authorize('view', $generation);

        return response()->json(['generation' => $generator->present($generation)]);
    }

    public function retry(ContentGeneration $generation, LessonGenerator $generator): JsonResponse
    {
        Gate::authorize('retry', $generation);

        $generator->retry($generation);

        return response()->json(['generation' => $generator->present($generation->refresh())], 202);
    }

    /**
     * One image for an editor slot, optionally attached to its target.
     */
    public function image(GenerateImageRequest $request, LessonGenerator $generator): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $generation = $generator->startImage($user, $request->imageData(), $request->hotelId());
        } catch (AiLimitReached $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        return response()->json(['generation' => $generator->present($generation->refresh())], 202);
    }

    /**
     * The courses a generated lesson may be added to: the department's
     * courses that are shared or of the chosen hotel, within the admin's
     * reach (ROLE-02).
     */
    public function courses(Request $request): JsonResponse
    {
        Gate::authorize('create', Course::class);

        /** @var User $user */
        $user = $request->user();

        $department = (string) $request->query('department', '0');
        $hotel = $request->query('hotel');
        $hotelId = is_string($hotel) && ctype_digit($hotel) ? (int) $hotel : null;

        $courses = Course::query()
            ->notArchived()
            // "essentials" lists the English Essentials topics (2026-10-18).
            ->when(
                $department === ContentTree::ESSENTIALS,
                fn (Builder $query) => $query->essentials(),
                fn (Builder $query) => $query->where('department_id', (int) $department)->where('track', 'department'),
            )
            ->where(fn (Builder $query) => $hotelId === null ? $query->whereNull('hotel_id') : $query->where('hotel_id', $hotelId))
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(fn (Course $course): bool => $user->can('update', $course));

        return response()->json([
            'courses' => $courses->map(fn (Course $course): array => [
                'value' => (string) $course->id,
                'label' => $course->title,
                'status' => $course->status->value,
            ])->values()->all(),
        ]);
    }
}
