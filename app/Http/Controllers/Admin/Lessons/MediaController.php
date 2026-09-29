<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\StoreMediaRequest;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Content\ContentTree;
use App\Services\Content\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The image library (MED-01, MED-02, MED-03, MED-07, SEC-04). Uploads go
 * through StoreMediaRequest (type, size, alt text) and MediaService (UUID
 * name, GD resize, thumb variant, audit row). The picker reads JSON.
 */
class MediaController extends Controller
{
    /**
     * The picker's page of images (MED-02: Choose / Replace).
     */
    public function index(Request $request, MediaService $media): JsonResponse
    {
        Gate::authorize('viewAny', Lesson::class);

        /** @var User $user */
        $user = $request->user();
        $tab = (string) $request->query('lib', 'guesvia-library');
        $libraries = match ($tab) {
            'my-images' => [MediaLibrary::MyImages],
            'icons-stickers' => [MediaLibrary::IconsStickers],
            default => [MediaLibrary::GuesviaLibrary, MediaLibrary::Seed],
        };
        $category = (string) $request->query('libCategory', ContentTree::ALL_CATEGORIES);
        $search = trim((string) $request->query('libSearch', ''));
        // Audio and video slots browse their own kind (client report
        // 2026-09-29: "choose from the Library").
        $kind = MediaKind::tryFrom((string) $request->query('kind', 'image'));
        $kind = in_array($kind, [MediaKind::Audio, MediaKind::Video], true) ? $kind : MediaKind::Image;

        $page = $media->library($user, $libraries, $category, $search, $kind)
            ->paginate(24)
            ->withQueryString();

        return response()->json([
            'images' => array_map(fn (MediaAsset $asset): array => $media->row($asset), $page->items()),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
        ]);
    }

    public function store(StoreMediaRequest $request, MediaService $media): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $asset = $media->upload($request->uploadedFile(), $request->mediaData(), $user);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['image' => $media->row($asset)], 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was uploaded.', ['name' => $asset->label ?? $asset->original_name ?? 'Image'])]);

        return back();
    }
}
