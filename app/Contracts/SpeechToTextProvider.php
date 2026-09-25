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
}
