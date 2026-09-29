# 0009 — Voice call latency and stored guest voices

Status (2026-09-26): built and verified locally (see Verification). Not deployed.
User request: "the AI in voice is too slow … fix the latency, whether with speech-to-text + text-to-speech or a better voice agent … store the voices that get generated, and let the AI decide whether to use a stored one or generate a new one, to save the Super Admin's AI spend."

Requirement IDs: RP-03, RP-04, RP-05, RP-06, RP-11, RP-13, TTS-02, TTS-03, AIL-01..04, API-02..04, SEC-03, PERF-04, PROG-04, DATA-05, ROLE-02.

## Why calls were slow (measured)

| Cause                                                                       | Effect                                                                                            | Fix                                                                                                               |
| --------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Flux `eot_threshold` 0.85 with Deepgram's default `eot_timeout_ms` **5000** | A hesitant learner who never sounds "finished" waited **5 s of silence** before every guest reply | `eot_timeout_ms` is now a setting, default **2000**; `eot_threshold` default 0.8                                  |
| No early end-of-turn                                                        | The reply was only started once the turn was final                                                | `eager_eot_threshold` 0.5: the reply is prepared while the learner finishes (both engines)                        |
| Qwen proxy answered Deepgram non-streaming                                  | Deepgram waited for the whole Qwen reply before speaking                                          | `VoiceAgentLlmController` relays Qwen's SSE stream chunk by chunk (`stream_options.include_usage` keeps metering) |
| Mic sent at 48 kHz linear16                                                 | 768 kb/s up: late audio on phone connections                                                      | Default 16 kHz (Flux native); the worklet averages samples when downsampling (no aliasing)                        |
| ElevenLabs default `eleven_multilingual_v2`                                 | ~1 s slower per reply                                                                             | Default `eleven_flash_v2_5`                                                                                       |
| Waiting for a whole TTS file                                                | Deepgram `/v1/speak`: first byte 0.55–0.9 s, whole 4-s sentence **~2.4 s** from this network      | New lines are **streamed** on Deepgram's speech socket: first audio **~170 ms**                                   |

Stored settings rows keep the values an admin saved; the new fields (`eagerEotThreshold`, `eotTimeoutMs`, `engine`, `reuseStoredLines`) take their defaults until saved.

## Two engines (Super Admin → AI Scenarios → Voice call settings → Engine)

**`pipeline` — Fast engine with stored voices (default).** The browser orchestrates; Hostinger runs PHP only, so nothing needs a long-running server.

```
mic ─16 kHz PCM─▶ Deepgram Flux (wss /v2/listen, grant token)
                    │ EagerEndOfTurn / TurnResumed / EndOfTurn
                    ▼
browser ──POST /learn/roleplay/attempts/{a}/voice/reply {turn, rev, text}──▶ VoiceReplyService
                    ◀── {text, audioUrl|null, source: reused|new, limitReached, stale}
audioUrl  → play the stored MP3 (fetched + decoded once per call)
null      → Deepgram speech socket (wss /v1/speak, same grant token) voices it live,
            RecordVoiceLine records it on the server for next time
```

- One grant token (`/v1/auth/grant`) opens both sockets at call start; the speech socket was measured alive after 80 s idle.
- The reply is requested at `EagerEndOfTurn` and played only once `EndOfTurn` confirms the same words; `TurnResumed` discards it. `rev` counts requests per turn: an older request finishing late is `stale` and never overwrites the newer one.
- Barge-in: `StartOfTurn` stops playback and sends `Clear` to the speech socket.
- Fallbacks: no recording and no speech socket → the browser's own `speechSynthesis`; Qwen refuses → 502 with a message, the employee's line is still stored.
- The greeting is a stored line too: the first call streams it and queues the recording; every later call plays it from storage (session start 1.8 s → 0.45 s measured).

**`agent` — Deepgram Voice Agent (spec 0004)**, now with `eager_eot_threshold`, `eot_timeout_ms`, 16 kHz input and the streaming Qwen proxy. It cannot use stored audio (the agent only speaks through its own TTS) and bills per connected minute.

The fast engine falls back to `agent` when no OpenAI-compatible text model is configured (`VoiceAgentSettings::pipelineAvailable()`), and the settings save refuses `pipeline` then.

## The stored-voice bank and the AI's choice

- `voice_lines` (scenario or `NULL` = generic, voice, text, hash, `audio_clip_id`, `reusable`, `uses`, `reuses`); the audio is an ordinary `audio_clips` row keyed by text + voice, so one sentence is one file across scenarios and the admin audio tools still apply (TTS-03).
- Every turn, `VoiceReplyService` gives Qwen up to 24 **recorded, reusable** lines of the scenario (most used first) plus the generic ones, and asks for either `{"reuse": n}` or `{"say": "…", "keep": true|false}`.
    - `reuse` → the recording plays at once; no speech is bought. Counted in `reuses`.
    - `say` → a new line, streamed now and recorded once; `keep` is the AI's judgement whether it is general enough to offer again. A line with a name, number or detail the employee gave is stored but never offered (privacy).
- Generic lines ("Sorry, could you say that again?", "Thank you for your help. Goodbye!", …) are recorded by `WarmVoiceLines` on the first call in a voice.
- The admin can switch reuse off (`reuseStoredLines`); lines are still recorded.
- The settings dialog shows lines recorded, reuses and speech characters not paid for again (`VoiceLineBank::stats()`).

## Metering and limits

| What                          | `ai_usages`                                                                            | Account  |
| ----------------------------- | -------------------------------------------------------------------------------------- | -------- |
| Guest line from Qwen          | `roleplay_turn`, provider `qwen_voice_pipeline`, real tokens, no points                | Qwen     |
| A new recording               | `tts`, model = the Aura-2 voice, characters, attributed to the learner                 | Deepgram |
| The call's streamed listening | `voice_call`, model `deepgram-flux-stream`, seconds (`UsageMeter::VOICE_STREAM_MODEL`) | Deepgram |
| Agent engine call             | `voice_call`, model `deepgram-voice-agent` (unchanged)                                 | Deepgram |

Set a per-second price for `deepgram-flux-stream` in the owner console so the fast engine's calls are costed. The daily turn limit, the ten-minute voice points and the owner's credit apply on every reply; when spent, the guest says `VoiceReplyService::CLOSING_LINE` and the call ends (AIL-03).

`roleplay_attempts.voice_engine` records which engine ran each call.

## Decisions

1. **The reply is generated inside the request**, not a queued job: a live conversation cannot wait for a queue worker. Like the agent's Qwen proxy (spec 0004) it is the documented exception to "queue every AI call"; it has short timeouts (connect 5 s, total 15 s) and is throttled (60/min).
2. **Recording is queued** (`RecordVoiceLine`, `media` queue, unique per clip). A line may be synthesised twice the first time it is said (streamed + recorded): ~$0.002 per new line, falling as the bank fills.
3. Stored lines are English guest speech generated by AI, not learner data; they are kept like other generated audio.

## Found while verifying

- **The Qwen token-plan key in `.env` has exhausted its quota** (HTTP 429 `insufficient_quota`, 2026-09-26). Every Qwen feature fails until it is topped up. The fast engine now tells the learner "The AI text service has run out of quota. Please tell your administrator." instead of asking them to repeat.

## Verification (2026-09-26)

- `php artisan test --filter="VoicePipeline|VoiceAgent"`: 29 pass (`tests/Feature/Learn/Roleplay/VoicePipelineTest.php` is new: reuse, new line, streaming + background recording, speech outage, speculative/stale turns, daily limit, 403 for another learner / a manager / the admin route, ended call, admin test call, fallback to the agent, metering).
- Live, real Deepgram: `bash storage/harness/voice-pipeline-probe.sh` (grant token accepted by Flux; `EndOfTurn` ~20–100 ms after clean speech; stored reply 64 ms) and `node storage/harness/voice-tts-ws-probe.mjs` (first audio 165–168 ms; alive after 80 s idle).
- Real browser, end to end: `bash storage/harness/voice-browser-e2e.sh` — headless Chromium with a WAV as the microphone → Flux → reply endpoint → streamed voice. Guest voice started **487 ms and 267 ms after the learner stopped** (text model faked because of the Qwen quota; add Qwen's answer time, 0.5–1.5 s, partly hidden by the early end-of-turn). A speculative reply overtaken by `TurnResumed` was correctly not played.
- Gates: full `php artisan test` 994 pass / 13 skipped (the 3 `TranslationsCoverageTest` failures under `DB_DATABASE=:memory:` pass on a migrated database: that test has no `RefreshDatabase`); PHPStan level 7 clean on the whole app; Pint; `vp check` (469 files) and `vue-tsc` clean.
- Settings dialog screenshots: `bash storage/harness/voice-settings-shot.sh` → `storage/harness/after/0009/` (1280×853, 1240×698, 390×844; no horizontal overflow).
- Not tried: a real microphone on iOS Safari / Android Chrome; the Qwen reply live (quota); Hostinger's timings.
