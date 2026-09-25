<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\ResolvesOwner;
use App\Http\Requests\Owner\ModelPricesRequest;
use App\Models\AiModelPrice;
use App\Models\AuditLog;
use App\Services\Owner\ApiCredit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The price table behind every cost figure and the dollar credit (API-03;
 * spec 0005 §4.3, moved to the owner by spec 0007 D8). Saved as a whole:
 * rows missing from the request are removed. Every change is audited.
 */
class ModelPriceController extends Controller
{
    use ResolvesOwner;

    public function update(ModelPricesRequest $request, ApiCredit $credit): RedirectResponse
    {
        $owner = $this->owner($request);
        $rows = $request->rows();
        $models = array_map(fn (array $row): string => $row['model'], $rows);

        foreach ($rows as $row) {
            $price = AiModelPrice::query()->firstOrNew(['model' => $row['model']]);
            $before = $price->exists ? $price->only(['unit', 'input_per_million', 'output_per_million']) : null;

            $price->fill([
                'unit' => $row['unit'],
                'input_per_million' => $row['input'],
                'output_per_million' => $row['output'],
            ]);

            if (! $price->exists || $price->isDirty()) {
                $price->save();
                AuditLog::recordByOwner($price, $before === null ? 'ai_price.created' : 'ai_price.updated', $owner, [
                    'model' => $price->model,
                    'from' => $before,
                    'to' => $price->only(['unit', 'input_per_million', 'output_per_million']),
                ]);
            }
        }

        AiModelPrice::query()->whereNotIn('model', $models)->get()->each(function (AiModelPrice $price) use ($owner): void {
            AuditLog::recordByOwner($price, 'ai_price.removed', $owner, ['model' => $price->model]);
            $price->delete();
        });

        $credit->forget();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prices saved. Usage with no stored cost is re-costed at these prices.')]);

        return back();
    }
}
