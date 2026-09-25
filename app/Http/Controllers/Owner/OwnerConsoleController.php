<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\Owner\OwnerConsole;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The owner console (spec 0007): the paid API accounts, their keys,
 * credit and recharges, and the price table.
 */
class OwnerConsoleController extends Controller
{
    public function __invoke(OwnerConsole $console): Response
    {
        return Inertia::render('owner/Dashboard', $console->payload());
    }
}
