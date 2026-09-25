<?php

namespace App\Services\Content;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\MediaLibrary;
use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Builds the Lessons & Content page from the query string (CMS-01, CMS-03,
 * CMS-04, BLD-01, BLD-02, MED-02; spec 0003 Part D).
 *
 * Every select, the tree's expanded state, the active tab and the library
 * filters live in the URL, so a reload lands on the same lesson. The
 * resolution order is hotel → department → course → unit → lesson, each
 * falling back to the first option in reach when the query names nothing
 * (or something the user may not see, ROLE-02).
 */
class ContentTree
{
    public const string SHARED = 'shared';

    public const string ALL_CATEGORIES = 'all-categories';

    public const int LIBRARY_PANEL_SIZE = 8;

    public const string DIRECTORY_ALL_HOTELS = 'all-hotels';

    public const string DIRECTORY_ALL_DEPARTMENTS = 'all-departments';

    public const string DIRECTORY_ALL_COURSES = 'all-courses';

    public const string DIRECTORY_ALL_STATUSES = 'all-statuses';

    /** The directory gives admins a quick, predictable density choice. */
    public const array DIRECTORY_PAGE_SIZES = [10, 20, 50];

    /** @var array<string, MediaLibrary> */
    public const array LIBRARY_TABS = [
        'my-images' => MediaLibrary::MyImages,
        'guesvia-library' => MediaLibrary::GuesviaLibrary,
        'icons-stickers' => MediaLibrary::IconsStickers,
    ];

    public function __construct(
        private readonly BlockShaper $blocks,
        private readonly MediaService $media,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request, User $user): array
    {
        $hotels = $this->hotels();
        $hotelKey = $this->pickHotel((string) $request->query('hotel', ''), $hotels);
        $hotelId = $hotelKey === self::SHARED ? null : (int) $hotelKey;

        $departments = $this->departments($hotelId);
        $departmentId = $this->pickDepartment((int) $request->query('department', 0), $departments, $hotelKey, $hotelId);

        $courses = $this->courses($departmentId, $hotelKey, $hotelId);
        $course = $this->pick($courses, (int) $request->query('course', 0));

        /** @var Collection<int, Unit> $units */
        $units = $course->units ?? new Collection;
        $unit = $this->pick($units, (int) $request->query('unit', 0));

        /** @var Collection<int, Lesson> $lessons */
        $lessons = $unit->lessons ?? new Collection;
        $lesson = $this->pick($lessons, (int) $request->query('lesson', 0));

        $open = array_values(array_filter(explode(',', (string) $request->query('open', ''))));
        $tab = (string) $request->query('tab', 'content');
        $tab = in_array($tab, ['content', 'preview', 'settings', 'materials', 'roleplay', 'quiz'], true) ? $tab : 'content';

        $directory = $this->lessonDirectory($request, $user);

        return [
            'filters' => [
                'hotel' => $hotelKey,
                'department' => (string) $departmentId,
                'course' => (string) ($course->id ?? ''),
                'unit' => (string) ($unit->id ?? ''),
                'lesson' => (string) ($lesson->id ?? ''),
                'hotels' => [
                    ['value' => self::SHARED, 'label' => __('Shared (all hotels)')],
                    ...$hotels->map(fn (Hotel $hotel): array => ['value' => (string) $hotel->id, 'label' => $hotel->name])->all(),
                ],
                'departments' => $departments->map(fn (Department $department): array => ['value' => (string) $department->id, 'label' => $department->name])->values()->all(),
                'courses' => $courses->map(fn (Course $row): array => ['value' => (string) $row->id, 'label' => $row->title])->values()->all(),
                'units' => $units->values()->map(fn (Unit $row, int $index): array => ['value' => (string) $row->id, 'label' => ($index + 1).'. '.$row->title])->all(),
                'lessons' => $lessons->values()->map(fn (Lesson $row, int $index): array => ['value' => (string) $row->id, 'label' => ($index + 1).'. '.$row->title])->all(),
                'open' => $open,
            ],
            'tabs' => [
                ['key' => 'content', 'label' => __('Lesson Content')],
                ['key' => 'preview', 'label' => __('Preview')],
                ['key' => 'settings', 'label' => __('Settings')],
                ['key' => 'materials', 'label' => __('Materials')],
                ['key' => 'roleplay', 'label' => __('AI Role-play')],
                ['key' => 'quiz', 'label' => __('Quiz / Practice')],
            ],
            'activeTab' => $tab,
            // The directory is the landing surface. The builder opens only
            // after a lesson is selected or created (CMS-01).
            'builderOpen' => $request->query('lesson') !== null,
            // The directory is separate from the expandable builder tree so
            // admins can identify department and hotel scope before opening
            // the editor (CMS-01, CMS-04).
            'lessonDirectory' => $directory['rows'],
            'directoryStats' => $directory['stats'],
            'directoryFilters' => $directory['filters'],
            'directoryPagination' => $directory['pagination'],
            'courses' => $this->tree($courses, $course, $unit, $lesson, $open),
            'editor' => $this->editor($lesson, $hotels, $departments),
            'blocks' => $this->palette(),
            'library' => $this->library($request, $user),
            'lessonBlocks' => $lesson === null ? [] : $this->blocks->forLesson($lesson),
            'scenarios' => $this->scenarios($departmentId, $hotelKey, $hotelId),
            'blockTypes' => array_map(fn (BlockType $type): array => [
                'value' => $type->value,
                'label' => $type->heading(),
                'stepLabel' => $type->stepLabel(),
                'description' => $type->description(),
                'tone' => $type->tone(),
                'icon' => $type->icon(),
            ], BlockType::cases()),
        ];
    }

    // ----------------------------------------------------------- selection

    /**
     * @return Collection<int, Hotel>
     */
    private function hotels(): Collection
    {
        return Hotel::query()->notArchived()->orderBy('id')->get(['id', 'name']);
    }

    /**
     * @param  Collection<int, Hotel>  $hotels
     */
    private function pickHotel(string $requested, Collection $hotels): string
    {
        if ($requested === self::SHARED) {
            return self::SHARED;
        }

        if ($requested !== '' && $hotels->contains(fn (Hotel $hotel): bool => (string) $hotel->id === $requested)) {
            return $requested;
        }

        $first = $hotels->first();

        return $first === null ? self::SHARED : (string) $first->id;
    }

    /**
     * @return Collection<int, Department>
     */
    private function departments(?int $hotelId): Collection
    {
        $query = Department::query()->active()->orderBy('position')->orderBy('name');

        if ($hotelId === null) {
            $query->globalCatalogue();
        } else {
            $query->where(function (Builder $inner) use ($hotelId): void {
                $inner->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
            });
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Department>  $departments
     */
    private function pickDepartment(int $requested, Collection $departments, string $hotelKey, ?int $hotelId): int
    {
        if ($requested > 0 && $departments->contains('id', $requested)) {
            return $requested;
        }

        // The first department that has a course in reach, so the page never
        // opens on an empty tree while content exists.
        $withCourses = $this->courseScope($hotelKey, $hotelId)
            ->whereIn('department_id', $departments->pluck('id'))
            ->orderBy('department_id')
            ->value('department_id');

        if (is_numeric($withCourses)) {
            $ordered = $departments->firstWhere('id', (int) $withCourses);

            return $ordered->id ?? (int) $withCourses;
        }

        return (int) ($departments->first()->id ?? 0);
    }

    /**
     * @return Builder<Course>
     */
    private function courseScope(string $hotelKey, ?int $hotelId): Builder
    {
        $query = Course::query();

        if ($hotelKey === self::SHARED) {
            $query->whereNull('hotel_id');
        } else {
            $query->sharedOrFor($hotelId);
        }

        return $query;
    }

    /**
     * @return Collection<int, Course>
     */
    private function courses(int $departmentId, string $hotelKey, ?int $hotelId): Collection
    {
        return $this->courseScope($hotelKey, $hotelId)
            ->where('department_id', $departmentId)
            ->with(['units.lessons'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @template TModel of Course|Unit|Lesson
     *
     * @param  Collection<int, TModel>  $rows
     * @return TModel|null
     */
    private function pick(Collection $rows, int $requested)
    {
        if ($requested > 0) {
            $match = $rows->first(fn ($row): bool => $row->id === $requested);

            if ($match !== null) {
                return $match;
            }
        }

        return $rows->first();
    }

    // ---------------------------------------------------------------- tree

    /**
     * @param  Collection<int, Course>  $courses
     * @param  list<string>  $open
     * @return list<array<string, mixed>>
     */
    private function tree(Collection $courses, ?Course $course, ?Unit $unit, ?Lesson $lesson, array $open): array
    {
        return array_values($courses->map(function (Course $row) use ($course, $unit, $lesson, $open): array {
            $expanded = $row->id === $course?->id || in_array('c'.$row->id, $open, true);

            return [
                'id' => $row->id,
                'title' => $row->title,
                'tone' => $row->tone,
                'status' => $row->status->value,
                'expanded' => $expanded,
                'units' => $row->units->values()->map(function (Unit $unitRow, int $index) use ($unit, $lesson, $open): array {
                    $unitExpanded = $unitRow->id === $unit?->id || in_array('u'.$unitRow->id, $open, true);

                    return [
                        'id' => $unitRow->id,
                        'title' => __('Unit :n: :title', ['n' => $index + 1, 'title' => $unitRow->title]),
                        'status' => $unitRow->status->value,
                        'expanded' => $unitExpanded,
                        'lessons' => $unitRow->lessons->values()->map(fn (Lesson $lessonRow, int $lessonIndex): array => [
                            'id' => $lessonRow->id,
                            'title' => ($lessonIndex + 1).'. '.$lessonRow->title,
                            'status' => $lessonRow->status->value,
                            'active' => $lessonRow->id === $lesson?->id,
                        ])->all(),
                        'addLessonLabel' => __('+ Add Lesson'),
                    ];
                })->all(),
            ];
        })->all());
    }

    // -------------------------------------------------------------- editor

    /**
     * @param  Collection<int, Hotel>  $hotels
     * @param  Collection<int, Department>  $departments
     * @return array<string, mixed>
     */
    private function editor(?Lesson $lesson, Collection $hotels, Collection $departments): array
    {
        if ($lesson === null) {
            return [
                'id' => null,
                'title' => '',
                'titleCount' => '0/100',
                'coverUrl' => null,
                'coverAlt' => '',
                'coverMediaId' => null,
                'introduction' => '',
                'introductionCount' => '0/500',
                'objectives' => [],
                'status' => 'draft',
                'publishedAt' => null,
                'estimatedMinutes' => null,
                'completionCondition' => null,
                'unitId' => null,
                'hotelLabel' => null,
                'departmentLabel' => null,
                'previewUrl' => null,
                'updatedAt' => null,
            ];
        }

        $course = $lesson->course()->first();
        $cover = $lesson->cover()->first();
        $title = $lesson->title;
        $introduction = $lesson->introduction ?? '';

        return [
            'id' => $lesson->id,
            'title' => $title,
            'titleCount' => mb_strlen($title).'/100',
            'coverUrl' => $cover?->url(),
            'coverAlt' => $cover->alt_text ?? '',
            'coverMediaId' => $lesson->cover_media_id,
            'introduction' => $introduction,
            'introductionCount' => mb_strlen($introduction).'/500',
            'objectives' => array_values(array_filter($lesson->objectives ?? [], 'is_string')),
            'status' => $lesson->status->value,
            'publishedAt' => $lesson->published_at?->toIso8601String(),
            'estimatedMinutes' => $lesson->estimated_minutes,
            'completionCondition' => $lesson->completion_condition,
            'unitId' => $lesson->unit_id,
            'hotelLabel' => $lesson->hotel_id === null
                ? __('Shared (all hotels)')
                : ($hotels->firstWhere('id', $lesson->hotel_id)->name ?? __('Hotel #:id', ['id' => $lesson->hotel_id])),
            'departmentLabel' => $course === null ? null : ($departments->firstWhere('id', $course->department_id)->name ?? null),
            'previewUrl' => route('lessons.preview', ['lesson' => $lesson]),
            'updatedAt' => $lesson->updated_at?->toIso8601String(),
        ];
    }

    // ------------------------------------------------------------- palette

    /**
     * The twelve tiles of the mockup, each mapped to the block type it adds
     * (BLD-02). "Downloadable File" has no block type yet and is disabled.
     *
     * @return list<array{id: string, label: string, icon: string, tone: string, type: string|null}>
     */
    private function palette(): array
    {
        return [
            ['id' => 'situation', 'label' => __('Situation'), 'icon' => 'situation', 'tone' => 'brand', 'type' => BlockType::Situation->value],
            ['id' => 'vocabulary', 'label' => __('Vocabulary'), 'icon' => 'vocabulary', 'tone' => 'success', 'type' => BlockType::Vocabulary->value],
            ['id' => 'expressions', 'label' => __('Useful Expressions'), 'icon' => 'expressions', 'tone' => 'warning', 'type' => BlockType::Expressions->value],
            ['id' => 'dialogue', 'label' => __('Dialogue'), 'icon' => 'dialogue', 'tone' => 'ai', 'type' => BlockType::Dialogue->value],
            ['id' => 'audio', 'label' => __('Watch & Listen'), 'icon' => 'audio', 'tone' => 'brand', 'type' => BlockType::ListenRepeat->value],
            ['id' => 'gallery', 'label' => __('Image / Gallery'), 'icon' => 'image', 'tone' => 'success', 'type' => BlockType::Image->value],
            ['id' => 'video', 'label' => __('Video'), 'icon' => 'video', 'tone' => 'danger', 'type' => BlockType::Video->value],
            ['id' => 'practice', 'label' => __('Practice Activity'), 'icon' => 'practice', 'tone' => 'warning', 'type' => BlockType::Practice->value],
            ['id' => 'roleplay', 'label' => __('AI Role-play'), 'icon' => 'roleplay', 'tone' => 'brand', 'type' => BlockType::AiRoleplay->value],
            ['id' => 'quiz', 'label' => __('Quiz / Test'), 'icon' => 'quiz', 'tone' => 'ai', 'type' => BlockType::Practice->value],
            ['id' => 'download', 'label' => __('Downloadable File'), 'icon' => 'download', 'tone' => 'azure', 'type' => null],
            ['id' => 'note', 'label' => __('Note / Tip'), 'icon' => 'note', 'tone' => 'gold', 'type' => BlockType::Note->value],
        ];
    }

    // ------------------------------------------------------------- library

    /**
     * @return array<string, mixed>
     */
    private function library(Request $request, User $user): array
    {
        $search = trim((string) $request->query('libSearch', ''));
        $category = (string) $request->query('libCategory', self::ALL_CATEGORIES);
        $requestedTab = (string) $request->query('lib', '');

        $tab = array_key_exists($requestedTab, self::LIBRARY_TABS) ? $requestedTab : $this->defaultLibraryTab($user);

        // The client's library is seeded from their own mockups until they
        // upload real photos (spec 0003 Part A rule 9), so the seed rows show
        // under the Guesvia Library tab.
        $libraries = $tab === 'guesvia-library'
            ? [MediaLibrary::GuesviaLibrary, MediaLibrary::Seed]
            : [self::LIBRARY_TABS[$tab]];

        $query = $this->media->library($user, $libraries, $category, $search);

        $total = (clone $query)->count();
        $images = $query->limit(self::LIBRARY_PANEL_SIZE)->get();

        return [
            'activeTab' => $tab,
            'tabs' => array_map(fn (string $key, MediaLibrary $library): array => ['key' => $key, 'label' => $library->label()], array_keys(self::LIBRARY_TABS), self::LIBRARY_TABS),
            'search' => $search,
            'category' => $category,
            'categories' => [
                ['value' => self::ALL_CATEGORIES, 'label' => __('All Categories')],
                ...array_map(fn (string $name): array => ['value' => $name, 'label' => ucfirst($name)], $this->media->categories()),
            ],
            'images' => $images->map(fn (MediaAsset $asset): array => $this->media->row($asset))->all(),
            'total' => $total,
        ];
    }

    private function defaultLibraryTab(User $user): string
    {
        $mine = $this->media->library($user, MediaLibrary::MyImages, null, '')->exists();

        return $mine ? 'my-images' : 'guesvia-library';
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     stats: list<array{key: string, value: int, label: string, detail?: string}>,
     *     filters: array<string, mixed>,
     *     pagination: array{from: int, to: int, total: int, currentPage: int, lastPage: int, perPage: int, pages: list<int|string>}
     * }
     */
    private function lessonDirectory(Request $request, User $user): array
    {
        $filters = $this->directoryFiltersFrom($request, $user);
        $query = $this->directoryQuery($user, $filters);

        /** @var LengthAwarePaginator<int, Lesson> $page */
        $page = $query
            ->paginate($filters['perPage'], ['*'], 'directoryPage')
            ->withQueryString();

        return [
            'rows' => array_values($page->getCollection()->map(function (Lesson $lesson): array {
                $course = $lesson->course;
                $unit = $lesson->unit;
                $departmentName = $course === null ? null : $course->department->name;
                $hotelName = $course === null ? null : $course->hotel?->name;

                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'course' => $course->title,
                    'unit' => $unit->title,
                    'department' => (string) ($departmentName ?? __('Unassigned')),
                    'hotel' => (string) ($hotelName ?? __('All hotels')),
                    'hotelId' => $lesson->hotel_id === null ? null : (int) $lesson->hotel_id,
                    'departmentId' => $course === null ? null : (int) $course->department_id,
                    'courseId' => $lesson->course_id,
                    'unitId' => $lesson->unit_id,
                    'status' => $lesson->status->value,
                    // Keep the employee journey visible from the directory. The
                    // editor still exposes every block so admins can inspect and
                    // reorder the actual steps (LESSON-01, LESSON-02, CMS-01).
                    'steps' => (int) $lesson->visible_blocks_count,
                    'url' => route('lessons.edit', $lesson),
                ];
            })->all()),
            'stats' => $this->directoryStats($user),
            'filters' => $this->directoryFiltersPayload($filters, $user),
            'pagination' => $this->directoryPagination($page, $filters['perPage']),
        ];
    }

    /**
     * Directory query: filter and page the rows on the server so a large
     * content library never arrives in the browser at once (CMS-01, CMS-04).
     *
     * @param  array{search: string, hotel: string|null, department: int|null, course: int|null, status: ContentStatus|null, perPage: int}  $filters
     * @return Builder<Lesson>
     */
    private function directoryQuery(User $user, array $filters): Builder
    {
        $query = $this->directoryBase($user)
            ->with(['course.department', 'course.hotel', 'unit'])
            ->withCount('visibleBlocks')
            ->orderBy('course_id')
            ->orderBy('unit_id')
            ->orderBy('position')
            ->orderBy('id');

        if ($filters['hotel'] === 'shared') {
            $query->whereNull('lessons.hotel_id');
        } elseif ($filters['hotel'] !== null) {
            $query->where('lessons.hotel_id', (int) $filters['hotel']);
        }

        if ($filters['department'] !== null) {
            $query->whereHas('course', fn (Builder $course): Builder => $course->where('department_id', $filters['department']));
        }

        if ($filters['course'] !== null) {
            $query->where('lessons.course_id', $filters['course']);
        }

        if ($filters['status'] !== null) {
            $query->where('lessons.status', $filters['status']->value);
        }

        $search = $filters['search'];

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';

            $query->where(function (Builder $inner) use ($like): void {
                $inner
                    ->where('lessons.title', 'like', $like)
                    ->orWhereHas('course', fn (Builder $course): Builder => $course->where('title', 'like', $like))
                    ->orWhereHas('unit', fn (Builder $unit): Builder => $unit->where('title', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * The base visibility scope is shared by rows and stats, so the summary
     * can never disclose content outside the current admin's reach (ROLE-02).
     *
     * @return Builder<Lesson>
     */
    private function directoryBase(User $user): Builder
    {
        $query = Lesson::query();

        if ($user->hotel_id === null) {
            if (! $user->hasRole('super_admin')) {
                $query->whereNull('lessons.hotel_id');
            }
        } else {
            $query->where(function (Builder $scope) use ($user): void {
                $scope->whereNull('lessons.hotel_id')->orWhere('lessons.hotel_id', $user->hotel_id);
            });
        }

        return $query;
    }

    /**
     * @return array{search: string, hotel: string|null, department: int|null, course: int|null, status: ContentStatus|null, perPage: int}
     */
    private function directoryFiltersFrom(Request $request, User $user): array
    {
        $requestedHotel = (string) $request->query('directoryHotel', self::DIRECTORY_ALL_HOTELS);
        $hotelValues = array_column($this->directoryHotelOptions($user), 'value');

        if (! in_array($requestedHotel, $hotelValues, true)) {
            $requestedHotel = self::DIRECTORY_ALL_HOTELS;
        }

        $requestedStatus = ContentStatus::tryFrom((string) $request->query('directoryStatus', ''));
        $requestedPerPage = (int) $request->query('directoryPerPage', self::DIRECTORY_PAGE_SIZES[0]);

        return [
            'search' => trim((string) $request->query('directorySearch', '')),
            'hotel' => $requestedHotel === self::DIRECTORY_ALL_HOTELS ? null : $requestedHotel,
            'department' => $this->positiveInt($request->query('directoryDepartment')),
            'course' => $this->positiveInt($request->query('directoryCourse')),
            'status' => $requestedStatus,
            'perPage' => in_array($requestedPerPage, self::DIRECTORY_PAGE_SIZES, true)
                ? $requestedPerPage
                : self::DIRECTORY_PAGE_SIZES[0],
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /**
     * @param  array{search: string, hotel: string|null, department: int|null, course: int|null, status: ContentStatus|null, perPage: int}  $filters
     * @return array<string, mixed>
     */
    private function directoryFiltersPayload(array $filters, User $user): array
    {
        return [
            'search' => $filters['search'],
            'hotel' => $filters['hotel'] ?? self::DIRECTORY_ALL_HOTELS,
            'department' => $filters['department'] === null ? self::DIRECTORY_ALL_DEPARTMENTS : (string) $filters['department'],
            'course' => $filters['course'] === null ? self::DIRECTORY_ALL_COURSES : (string) $filters['course'],
            'status' => $filters['status']->value ?? self::DIRECTORY_ALL_STATUSES,
            'hotels' => [
                ['value' => self::DIRECTORY_ALL_HOTELS, 'label' => __('All Hotels')],
                ...$this->directoryHotelOptions($user),
            ],
            'departments' => [
                ['value' => self::DIRECTORY_ALL_DEPARTMENTS, 'label' => __('All Departments')],
                ...$this->directoryDepartmentOptions($user),
            ],
            'courses' => [
                ['value' => self::DIRECTORY_ALL_COURSES, 'label' => __('All Courses')],
                ...$this->directoryCourseOptions($user),
            ],
            'statuses' => [
                ['value' => self::DIRECTORY_ALL_STATUSES, 'label' => __('All Statuses')],
                ...array_map(static fn (ContentStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ], ContentStatus::cases()),
            ],
            'pageSizes' => self::DIRECTORY_PAGE_SIZES,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function directoryHotelOptions(User $user): array
    {
        $hotels = Hotel::query()->withoutGlobalScopes()->notArchived()->orderBy('name');

        if (! $user->hasRole('super_admin')) {
            $hotels->whereKey($user->hotel_id ?? 0);
        }

        return [
            ['value' => 'shared', 'label' => __('Shared (all hotels)')],
            ...array_values($hotels->get()->map(fn (Hotel $hotel): array => [
                'value' => (string) $hotel->id,
                'label' => $hotel->name,
            ])->all()),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function directoryDepartmentOptions(User $user): array
    {
        return array_values(Department::query()
            ->visibleTo($user)
            ->active()
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department): array => [
                'value' => (string) $department->id,
                'label' => $department->name,
            ])
            ->all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function directoryCourseOptions(User $user): array
    {
        $query = Course::query()->orderBy('position')->orderBy('title');

        if (! $user->hasRole('super_admin')) {
            $query->sharedOrFor($user->hotel_id);
        }

        return array_values($query->get()->map(fn (Course $course): array => [
            'value' => (string) $course->id,
            'label' => $course->title,
        ])->all());
    }

    /**
     * @return list<array{key: string, value: int, label: string, detail?: string}>
     */
    private function directoryStats(User $user): array
    {
        $base = $this->directoryBase($user);
        $total = (clone $base)->count();
        $published = (clone $base)->where('lessons.status', ContentStatus::Published->value)->count();
        $draft = (clone $base)->where('lessons.status', ContentStatus::Draft->value)->count();
        $steps = Block::query()
            ->where('is_visible', true)
            ->whereIn('lesson_id', (clone $base)->select('lessons.id'))
            ->count();

        return [
            ['key' => 'totalLessons', 'value' => $total, 'label' => __('Total Lessons'), 'detail' => __('All visible content')],
            ['key' => 'publishedLessons', 'value' => $published, 'label' => __('Published'), 'detail' => __('Ready for learners')],
            ['key' => 'draftLessons', 'value' => $draft, 'label' => __('Drafts'), 'detail' => __('Still in progress')],
            ['key' => 'learningSteps', 'value' => $steps, 'label' => __('Learning Steps'), 'detail' => __('Across all lessons')],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Lesson>  $page
     * @return array{from: int, to: int, total: int, currentPage: int, lastPage: int, perPage: int, pages: list<int|string>}
     */
    private function directoryPagination(LengthAwarePaginator $page, int $perPage): array
    {
        return [
            'from' => (int) ($page->firstItem() ?? 0),
            'to' => (int) ($page->lastItem() ?? 0),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'perPage' => $perPage,
            'pages' => $this->directoryPageWindow($page->currentPage(), $page->lastPage()),
        ];
    }

    /**
     * @return list<int|string>
     */
    private function directoryPageWindow(int $current, int $last): array
    {
        $last = max(1, $last);

        if ($last <= 7) {
            return range(1, $last);
        }

        $start = max(1, min($current - 2, $last - 4));
        $end = min($last, $start + 4);
        $pages = [];

        if ($start > 1) {
            $pages[] = 1;

            if ($start > 2) {
                $pages[] = 'ellipsis';
            }
        }

        foreach (range($start, $end) as $number) {
            $pages[] = $number;
        }

        if ($end < $last) {
            if ($end < $last - 1) {
                $pages[] = 'ellipsis';
            }

            $pages[] = $last;
        }

        return $pages;
    }

    // ----------------------------------------------------------- scenarios

    /**
     * @return list<array{id: int, title: string, description: string|null, difficulty: string, icon: string, status: string}>
     */
    private function scenarios(int $departmentId, string $hotelKey, ?int $hotelId): array
    {
        $query = AiScenario::query()->where('department_id', $departmentId)->orderBy('title');

        if ($hotelKey === self::SHARED) {
            $query->whereNull('hotel_id');
        } else {
            $query->where(function (Builder $inner) use ($hotelId): void {
                $inner->whereNull('hotel_id');
                if ($hotelId !== null) {
                    $inner->orWhere('hotel_id', $hotelId);
                }
            });
        }

        return array_values($query->get()->map(fn (AiScenario $scenario): array => [
            'id' => $scenario->id,
            'title' => $scenario->title,
            'description' => $scenario->description,
            'difficulty' => $scenario->difficulty->value,
            'icon' => $scenario->icon,
            'status' => $scenario->status->value,
        ])->all());
    }
}
