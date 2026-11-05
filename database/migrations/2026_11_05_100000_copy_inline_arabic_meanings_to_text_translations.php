<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * "Translate meaning" instead of fixed Arabic fields (client request
 * 2026-11-05): a word's Arabic meaning and Arabic hotel example, a Listen &
 * Repeat sentence's, a dialogue line's and the video example's Arabic become
 * ordinary Show Meaning translations of their English text, stored once per
 * text and language in `text_translations`.
 *
 * This copies every inline Arabic value already written into the store as a
 * hand-written Arabic translation of its English text, so no learner loses a
 * meaning. A translation already written by hand is never touched; the first
 * lesson value of a text wins over an AI draft, a learner's request or a
 * failed draft, since it is what learners were shown until now. Running it
 * again changes nothing. The old columns and JSON keys stay (DATA-10).
 *
 * Plain query builder only, so it runs the same on SQLite and MySQL.
 */
return new class extends Migration
{
    private const int MAX_LENGTH = 1500;

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('text_translations')) {
            return;
        }

        DB::table('lexicon_items')
            ->select(['id', 'english_text', 'arabic_meaning', 'hotel_example', 'hotel_example_arabic'])
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    $this->copy($item->english_text, $item->arabic_meaning);
                    $this->copy($item->hotel_example, $item->hotel_example_arabic);
                }
            });

        DB::table('blocks')
            ->select(['id', 'type', 'settings'])
            ->whereIn('type', ['listen_repeat', 'dialogue', 'video'])
            ->orderBy('id')
            ->chunkById(200, function ($blocks): void {
                foreach ($blocks as $block) {
                    $settings = is_string($block->settings) ? json_decode($block->settings, true) : null;

                    if (! is_array($settings)) {
                        continue;
                    }

                    $rows = match ($block->type) {
                        'listen_repeat' => $settings['items'] ?? [],
                        'dialogue' => $settings['lines'] ?? [],
                        default => [$settings['example'] ?? null],
                    };

                    foreach (is_array($rows) ? $rows : [] as $row) {
                        if (is_array($row)) {
                            $this->copy($row['text'] ?? null, $row['arabic'] ?? null);
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        // Data only: the translations stay (DATA-10), the columns were kept.
    }

    private function copy(mixed $english, mixed $arabic): void
    {
        if (! is_string($english) || ! is_string($arabic)) {
            return;
        }

        $english = trim((string) preg_replace('/\s+/u', ' ', $english));
        $arabic = trim($arabic);

        if ($english === '' || $arabic === '' || mb_strlen($english) > self::MAX_LENGTH || preg_match('/\p{L}/u', $english) !== 1) {
            return;
        }

        // The same key TextTranslation::hashOf() builds.
        $hash = hash('sha256', mb_strtolower($english, 'UTF-8'));
        $now = now();

        $existing = DB::table('text_translations')
            ->where('hash', $hash)
            ->where('locale', 'ar')
            ->first(['id', 'source']);

        if ($existing === null) {
            DB::table('text_translations')->insert([
                'hash' => $hash,
                'locale' => 'ar',
                'source_text' => $english,
                'translation' => $arabic,
                'status' => 'done',
                'source' => 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        if ($existing->source === 'manual') {
            return;
        }

        DB::table('text_translations')->where('id', $existing->id)->update([
            'translation' => $arabic,
            'status' => 'done',
            'source' => 'manual',
            'failed_reason' => null,
            'updated_at' => $now,
        ]);
    }
};
