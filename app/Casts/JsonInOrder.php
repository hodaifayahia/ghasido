<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A JSON array cast that puts a map's keys back in their defined order.
 *
 * MySQL's JSON type stores objects with its own key order, so criteria saved
 * as {"task_completion": …, "accuracy": …} would come back alphabetised and
 * show up in the wrong order on the feedback screens. Nothing else changes:
 * the stored JSON and every value stay the same.
 *
 * Arguments: the path of the map inside the value (`-` for the value
 * itself), then the keys in order. Keys not listed follow in their stored
 * order. Usage: `JsonInOrder::class.':criteria,task_completion,accuracy'`.
 *
 * @implements CastsAttributes<array<array-key, mixed>|null, mixed>
 */
final class JsonInOrder implements CastsAttributes
{
    /** @var list<string> */
    private array $order;

    public function __construct(private readonly string $path = '-', string ...$order)
    {
        $this->order = array_values($order);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<array-key, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        if (! is_array($decoded)) {
            return null;
        }

        if ($this->path === '-') {
            return $this->ordered($decoded);
        }

        if (is_array($decoded[$this->path] ?? null)) {
            $decoded[$this->path] = $this->ordered($decoded[$this->path]);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value);

        return $json === false ? null : $json;
    }

    /**
     * @param  array<array-key, mixed>  $map
     * @return array<array-key, mixed>
     */
    private function ordered(array $map): array
    {
        if (array_is_list($map)) {
            return $map;
        }

        $sorted = [];

        foreach ($this->order as $name) {
            if (array_key_exists($name, $map)) {
                $sorted[$name] = $map[$name];
            }
        }

        return $sorted + $map;
    }
}
