<?php

namespace App\Policies;

use App\Enums\MediaLibrary;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\MediaAsset;
use App\Models\User;

/**
 * Who may read a stored file (MED-02, PRIV-04, SEC-04, spec 0003 B.3).
 *
 * Public disk files are lesson media: anyone signed in may see them, which
 * is also what the storage URL already allows. Private files are learner
 * recordings, served only through `media.show`. A learner's recording is
 * readable by the learner who made it and by whoever holds
 * `transcripts.view` (the Super Admin; ROLE-04, PRIV-04): a coworker, the
 * hotel's manager or its Hotel Admin is refused, even inside the same hotel.
 * Any other private file stays within its hotel's back office.
 */
class MediaAssetPolicy
{
    public function view(User $user, MediaAsset $asset): bool
    {
        if ($asset->isPublic()) {
            return true;
        }

        if ($asset->uploaded_by !== null && $asset->uploaded_by === $user->id) {
            return true;
        }

        // Security audit 2026-11-08: transcripts.view granted to a
        // hotel-bound role reads its own hotel's recordings only; across
        // hotels it is the Super Admin's alone (ROLE-02, ROLE-04, PRIV-04).
        if ($asset->library === MediaLibrary::Recordings) {
            return $user->hasRole(Role::SuperAdmin->value)
                || ($user->can(Permission::TranscriptsView->value)
                    && $asset->hotel_id !== null
                    && $asset->hotel_id === $user->hotel_id);
        }

        return $asset->hotel_id !== null
            && $asset->hotel_id === $user->hotel_id
            && ! $user->hasRole(Role::Employee->value);
    }

    /**
     * Uploading into the CMS library is a content capability (MED-01).
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::LessonsManage->value);
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $user->can(Permission::LessonsManage->value)
            && ($asset->hotel_id === null || $asset->hotel_id === $user->hotel_id);
    }

    /**
     * Files are never deleted from under an answer (DATA-10); only library
     * images with no learner data behind them may go.
     */
    public function delete(User $user, MediaAsset $asset): bool
    {
        return $this->update($user, $asset) && $asset->library->isPickable();
    }
}
