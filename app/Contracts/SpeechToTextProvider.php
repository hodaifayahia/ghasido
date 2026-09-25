<?php

namespace App\Contracts;

/**
 * Speech-to-text behind one interface (RP-02 voice input, API-04).
 */
interface SpeechToTextProvider
{
    /**
     * @param  string  $absolutePath  a readable local path to the recording
     * @param  string  $mime  its MIME type, e.g. audio/webm
     * @return string the transcript, possibly empty
     */
    public function transcribe(string $absolutePath, string $mime): string;

    /**
     * Word by word, with each word's confidence and timing, for the
     * pronunciation check (spec 0006 §5). Numbers stay words ("three", never
     * "3") and hesitations ("um") are kept, so they can be counted.
     *
     * @param  list<string>  $keyterms  words to listen for (the hinted second listen); empty = a free listen
     */
    public function transcribeWords(string $absolutePath, string $mime, array $keyterms = []): WordTranscript;
}
