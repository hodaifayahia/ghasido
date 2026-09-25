<?php

namespace App\Services\Hotels;

use App\Enums\CapacityState;
use App\Enums\HotelStatus;
use App\Http\Resources\Hotels\HotelOverviewResource;
use App\Http\Resources\Hotels\HotelRowResource;
use App\Models\Hotel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The read side of the Hotels screen: filters in, page props out
 * (spec 0002, directory child).
 *
 * Composes the model's scopes (search, derived status, live capacity, the
 * fixed directory order) with the paginator, then hands each row to a
 * resource. The controller only calls this; it writes no query (AC-20).
 */
class HotelDirectory
{
    public const ALL_STATUSES = 'all-statuses';

    public const ALL_CAPACITIES = 'all-capacities';

    /**
     * @return array{stats: list<array<string, mixed>>, filters: array<string, mixed>, hotels: list<array<string, mixed>>, pagination: array<string, mixed>, overview: array<string, mixed>|null}
     */
    public function build(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', self::ALL_STATUSES);
        $capacity = (string) $request->query('capacity', self::ALL_CAPACITIES);
        $selected = (int) $request->query('hotel', 0);

        $page = $this->query($search, $status, $capacity)
            ->paginate(self::perPage())
            ->withQueryString();

        /** @var list<Hotel> $rows */
        $rows = $page->items();
        $from = (int) ($page->firstItem() ?? 0);

        $overview = $this->select($rows, $selected);

        return [
            'stats' => $this->stats(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'capacity' => $capacity,
                'statuses' => $this->statusOptions(),
                'capacities' => $this->capacityOptions(),
            ],
            'hotels' => array_map(
                fn (Hotel $hotel, int $index): array => (new HotelRowResource($hotel, $from + $index))->resolve($request),
                $rows,
                array_keys($rows),
            ),
            'pagination' => $this->pagination($page),
            'overview' => $overview === null ? null : (new HotelOverviewResource($overview))->resolve($request),
        ];
    }

    /**
     * @return Builder<Hotel>
     */
    private function query(string $search, string $status, string $capacity): Builder
    {
        $query = Hotel::query()->withSeatCounts()->directoryOrder()->search($search);

        $derived = HotelStatus::tryFrom($status);

        if ($derived === null) {
            // Archived hotels appear in no default list and can be filtered
            // back in (AC-3).
            $query->notArchived();
        } else {
            $query->withStatus($derived);
        }

        $state = CapacityState::tryFrom($capacity);

        if ($state !== null) {
            $query->withCapacity($state);
        }

        return $query;
    }

    /**
     * The sidebar opens on the first row of the current page and swaps when
     * View is clicked (AC-18). A `hotel` parameter that is not on this page
     * (right after a create, say) is still honoured when the hotel exists,
     * so the person sees what they just did.
     *
     * @param  list<Hotel>  $rows
     */
    private function select(array $rows, int $selected): ?Hotel
    {
        if ($selected > 0) {
            foreach ($rows as $row) {
                if ($row->id === $selected) {
                    return $row;
                }
            }

            $hotel = Hotel::query()->withSeatCounts()->find($selected);

            if ($hotel !== null) {
                return $hotel;
            }
        }

        return $rows[0] ?? null;
    }

    /**
     * @return list<array{key: string, value: int, label: string, detail?: string}>
     */
    private function stats(): array
    {
        $stats = Hotel::portfolioStats();
        $window = Hotel::expiringWithinDays();

        return [
            ['key' => 'totalHotels', 'value' => $stats['total'], 'label' => __('Total Hotels')],
            ['key' => 'activeContracts', 'value' => $stats['active'], 'label' => __('Active Contracts')],
            [
                'key' => 'expiringSoon',
                'value' => $stats['expiring'],
                'label' => __('Expiring Soon'),
                'detail' => __('Next :days days', ['days' => $window]),
            ],
            ['key' => 'pausedContracts', 'value' => $stats['paused'], 'label' => __('Paused Access')],
            [
                'key' => 'usedSeats',
                'value' => $stats['usedSeats'],
                'label' => __('Used Seats'),
                'detail' => __(':used / :allowed allocated', [
                    'used' => $stats['usedSeats'],
                    'allowed' => $stats['allowedSeats'],
                ]),
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => self::ALL_STATUSES, 'label' => __('All Statuses')],
            ['value' => HotelStatus::Pending->value, 'label' => __('Pending')],
            ['value' => HotelStatus::Active->value, 'label' => __('Active')],
            ['value' => HotelStatus::Expiring->value, 'label' => __('Expiring Soon')],
            ['value' => HotelStatus::Paused->value, 'label' => __('Paused')],
            ['value' => HotelStatus::Ended->value, 'label' => __('Ended')],
            ['value' => HotelStatus::Archived->value, 'label' => __('Archived')],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function capacityOptions(): array
    {
        return [
            ['value' => self::ALL_CAPACITIES, 'label' => __('All Seat States')],
            ['value' => CapacityState::Available->value, 'label' => __('Seats Available')],
            ['value' => CapacityState::Full->value, 'label' => __('At Capacity')],
            ['value' => CapacityState::Over->value, 'label' => __('Over Quota')],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Hotel>  $page
     * @return array{from: int, to: int, total: int, currentPage: int, lastPage: int, pages: list<int|string>}
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'from' => (int) ($page->firstItem() ?? 0),
            'to' => (int) ($page->lastItem() ?? 0),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'pages' => $this->pageWindow($page->currentPage(), $page->lastPage()),
        ];
    }

    /**
     * 1 … current-1 current current+1 … last, with the ends always present.
     *
     * @return list<int|string>
     */
    private function pageWindow(int $current, int $last): array
    {
        if ($last <= 7) {
            return range(1, max(1, $last));
        }

        $pages = [1];

        if ($current > 3) {
            $pages[] = 'ellipsis';
        }

        foreach (range(max(2, $current - 1), min($last - 1, $current + 1)) as $page) {
            $pages[] = $page;
        }

        if ($current < $last - 2) {
            $pages[] = 'ellipsis';
        }

        $pages[] = $last;

        return $pages;
    }

    public static function perPage(): int
    {
        return max(1, (int) config('guesvia.hotels.per_page', 15));
    }
}
