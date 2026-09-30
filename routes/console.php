<?php

use App\Jobs\RunAutomationRules;
use App\Services\Audio\AudioLibrary;
use App\Services\Audio\PlayableTextCollector;
use App\Services\VoiceAgent\VoiceCallService;
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

// Live voice calls whose page went away without hanging up are closed as of
// their last caption and metered like any other call (API-03, AIL-01; spec
// 0007, D9). A learner's own next call closes theirs too, so nothing is lost
// while the scheduler cron is not set up yet.
Schedule::call(fn (VoiceCallService $calls): int => $calls->closeStale())
    ->name('voice-calls:close-stale')
    ->everyTenMinutes()
    ->withoutOverlapping();
