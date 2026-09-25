<?php

use App\Jobs\RunAutomationRules;
use App\Services\Audio\AudioLibrary;
use App\Services\Audio\PlayableTextCollector;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('audio:generate-all', function (AudioLibrary $audio, PlayableTextCollector $playable): void {
    $inventory = $playable->forAllLessons();

    foreach ($inventory['texts'] as $text) {
        $audio->ensureBoth($text);
    }

    $this->info(sprintf(
        'Queued Normal and Slow audio for %d texts across %d lessons.',
        count($inventory['texts']),
        $inventory['lessons'],
    ));
})->purpose('Queue stored Normal and Slow audio for every word and lesson sentence (TTS-01, TTS-02)');

// Automatic reminders run once a day (REM-03, spec 0003 Part C). The job
// is unique and the runner skips anybody reached inside the repeat window,
// so a second tick on the same day sends nothing twice.
Schedule::job(new RunAutomationRules)->dailyAt('08:00');
