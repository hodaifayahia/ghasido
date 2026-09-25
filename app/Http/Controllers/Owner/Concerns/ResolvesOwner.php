<?php

namespace App\Http\Controllers\Owner\Concerns;

use App\Models\Owner;
use Illuminate\Http\Request;

/**
 * The signed-in owner on an owner route (the `owner` middleware has
 * already refused everyone else).
 */
trait ResolvesOwner
{
    protected function owner(Request $request): Owner
    {
        $owner = $request->user('owner');
        abort_unless($owner instanceof Owner, 401);

        return $owner;
    }
}
