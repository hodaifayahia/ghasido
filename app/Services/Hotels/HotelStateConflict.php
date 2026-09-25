<?php

namespace App\Services\Hotels;

use App\Models\Hotel;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * A hotel action asked for in a state that cannot take it: approving a hotel
 * that is not pending, pausing one that is not active, archiving one that is
 * already archived (spec 0002, State transitions).
 *
 * Renders as 409, which spec 0002's API surface names for every one of these,
 * so a stale button click is told apart from a permission boundary (403).
 */
class HotelStateConflict extends ConflictHttpException
{
    public static function for(Hotel $hotel, string $action, string $expected): self
    {
        return new self(__(
            'Cannot :action ":hotel": it is :state, not :expected.',
            [
                'action' => $action,
                'hotel' => $hotel->name,
                'state' => $hotel->access_state->value,
                'expected' => $expected,
            ],
        ));
    }
}
