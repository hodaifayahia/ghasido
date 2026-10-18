<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Content\ContentTree;
use App\Services\Tts\TtsSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Lessons & Content builder screen (CMS-01, CMS-02, CMS-03, CMS-05,
 * CMS-06, BLD-01, BLD-02, BLD-03, BLD-06; spec 0003 Part D).
 *
 * Read → authorize → delegate. ContentTree reads every prop from the query
 * string; no query is written here.
 */
class LessonsContentController extends Controller
{
    public function index(Request $request, ContentTree $tree, TtsSettings $tts): Response
    {
        Gate::authorize('viewAny', Lesson::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/LessonsContent', [
            ...$tree->build($request, $user),
            'tts' => $tts->payload(),
        ]);
    }

    /**
     * Where the builder lands after something was deleted from its Course
     * Structure tree: the first lesson left in the unit, else in the course,
     * so the builder stays open; the directory when none is left.
     *
     * @return array<string, int|string>
     */
    public static function builderQuery(Course $course, ?int $unitId = null): array
    {
        $query = [
            'hotel' => $course->hotel_id === null ? ContentTree::SHARED : $course->hotel_id,
            'department' => ContentTree::departmentKey($course),
            'course' => $course->id,
        ];

        // The unit's own lessons first, then the course in learner order.
        $next = $course->orderedLessons(false)
            ->whereNull('lessons.archived_at')
            ->whereNull('units.archived_at')
            ->reorder()
            ->orderByRaw('case when lessons.unit_id = ? then 0 else 1 end', [$unitId ?? 0])
            ->orderBy('units.position')
            ->orderBy('units.id')
            ->orderBy('lessons.position')
            ->orderBy('lessons.id')
            ->first();

        if ($next !== null) {
            $query['unit'] = $next->unit_id;
            $query['lesson'] = $next->id;
        } elseif ($unitId !== null) {
            $query['unit'] = $unitId;
        }

        return $query;
    }

    /**
     * Open the builder as its own page from the lesson directory (CMS-01).
     * The query-string builder remains available for the editor's live
     * selectors, but the first Edit navigation is a dedicated URL.
     */
    public function edit(Request $request, Lesson $lesson, ContentTree $tree, TtsSettings $tts): Response
    {
        Gate::authorize('view', $lesson);
        // Removed by "Delete": kept only for reports, never edited (DATA-10).
        abort_if($lesson->isArchived(), 404);

        $lesson->loadMissing('course');

        $request->query->set('hotel', $lesson->hotel_id === null ? ContentTree::SHARED : (string) $lesson->hotel_id);
        $request->query->set('department', $lesson->course === null ? '' : (string) ContentTree::departmentKey($lesson->course));
        $request->query->set('course', (string) $lesson->course_id);
        $request->query->set('unit', (string) $lesson->unit_id);
        $request->query->set('lesson', (string) $lesson->id);

        return $this->index($request, $tree, $tts);
    }
}
