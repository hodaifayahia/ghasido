<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
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
        $request->query->set('department', (string) ($lesson->course->department_id ?? ''));
        $request->query->set('course', (string) $lesson->course_id);
        $request->query->set('unit', (string) $lesson->unit_id);
        $request->query->set('lesson', (string) $lesson->id);

        return $this->index($request, $tree, $tts);
    }
}
