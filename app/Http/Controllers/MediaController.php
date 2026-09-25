<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a stored file behind MediaAssetPolicy (MED-02, PRIV-04, SEC-04;
 * spec 0003 B.3).
 *
 * A public asset is sent to its cacheable storage URL; a private one (a
 * learner's recording, an export) is streamed from the private disk only
 * after the policy says this user may read it. The Super Admin passes
 * through Gate::before; anyone else outside the owner's hotel gets a 403.
 */
class MediaController extends Controller
{
    public function show(MediaAsset $media): StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $media);

        if ($media->isPublic() && is_file(public_path('storage/'.$media->path))) {
            return redirect()->away(Storage::disk(MediaAsset::DISK_PUBLIC)->url($media->path));
        }

        if ($media->isPublic() && is_file(public_path($media->path))) {
            return redirect()->away(asset($media->path));
        }

        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->response(
            $media->path,
            $media->original_name ?? basename($media->path),
            ['Content-Type' => $media->mime],
        );
    }
}
