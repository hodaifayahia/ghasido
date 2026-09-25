<?php

namespace Database\Factories;

use App\Models\TtsSetting;
use App\Services\Tts\DeepgramTtsProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TtsSetting> */
class TtsSettingFactory extends Factory
{
    protected $model = TtsSetting::class;

    public function definition(): array
    {
        return [
            'voice' => DeepgramTtsProvider::DEFAULT_MODEL,
            'expressivity' => 0,
            'updated_by' => null,
        ];
    }
}
