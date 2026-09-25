# 0004 — AI media, lesson generation and live voice

Status (2026-09-23): built and verified. Gate: 639 tests pass, PHPStan 0, Pint,
vp check, vue-tsc clean. All new screens browser-checked at 1280×853, 1240×698,
390×844 (storage/harness/after/0004/). Live voice agent PASS over the browser
token path (`voice-agent:probe-session` + `storage/harness/voice-agent-probe.mjs`):
full employee sentence heard, in-character reply, first audio 0.8 s after the
employee stopped. Not yet tried: a real microphone in a real browser / iOS.

Queues: jobs are split (`App\Support\Queues`) into `interactive` (replies,
evaluations, drafts, checks), `default` (lesson/test generation, mail) and
`media` (audio, images), run by the `queue` and `queue-media` Sail workers — a
media backlog once blocked every AI feature (349 audio jobs ahead of a
role-play reply). Deepgram keys scoped only `account:write` get 403 from
`/v1/auth/grant`; use a key with Member permissions or higher.

Built: live voice call (`components/roleplay/VoiceCall.vue`, `useVoiceAgent.ts`,
`Services/VoiceAgent/*`, admin "Voice call settings" + "Test voice call");
lesson/course generation from one prompt with images + audio
(`Services/Content/LessonGenerator.php`, `Jobs/GenerateLessonFromPrompt`,
`content_generations`); DashScope images (sync fallback: this key refuses async
calls); Deepgram STT + spoken-answer evaluation; AI test-question generation incl.
paired Post-tests; stored listening audio; publishing a lesson publishes its draft
unit/course. `tests/TestCase.php` forces fake providers so tests never bill.

Open decisions for the client: (1) an empty voice call (never connected) is
deleted so a network failure doesn't burn an attempt — a DATA-10 exception;
(2) RESOLVED 2026-09-23 (client): a department with no published Pre-test
(shared or the learner's hotel) has its lessons UNLOCKED — gate 1 applies
only when a published Pre-test exists (`User::lessonsUnlocked()`, used by
LessonPolicy, LessonNavigator, LessonsController, JourneyService; shared
`journey.lessonsUnlocked`). The Post-test gate follows the same rule;
(3) voice-agent "think" defaults to Deepgram-managed gpt-4o-mini (billed by
Deepgram); Qwen needs a public https APP_URL.

Requirement IDs: GEN-01..04, TTS-01..05, RP-01..13,
AIE-01..05, TEST-06..09, STT (RESP-05), API-02..04, SEC-03, AIL-01..04, PERF-04,
ORG-04, CMS-04, JOURNEY-03.

## What was broken (fixed 2026-09-22)

| Symptom                               | Root cause                                                                                                                                  | Fix                                                                                                             |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| "Generate audio" never produced audio | `.env` had `TTS_PROVIDER=deepgram` with an empty `DEEPGRAM_API_KEY`                                                                         | key set in `.env` (never committed); Deepgram errors now surface their own message in `failed_reason`           |
| Qwen generation failed / hung         | Qwen 3.x are reasoning models; `enable_thinking` was never disabled, so replies were slow or empty. One 90 s timeout covered connecting too | `enable_thinking:false` for Qwen, 15 s connect timeout, 180 s reply timeout, retries only on connection/429/5xx |
| Slow AI jobs could run twice          | `DB_QUEUE_RETRY_AFTER` 90 s < worker `--timeout=120`                                                                                        | `DB_QUEUE_RETRY_AFTER=960`, worker `--timeout=900`, job `$timeout=600`                                          |

Verified live from WSL: Deepgram `flux-hannah-en` → MP3 in ~17 s; `qwen3.8-max`
lexicon draft in ~20 s. Probe: `php artisan tinker storage/harness/probe-providers.php`.

## Providers and models (configuration only — API-04)

| Purpose                                                 | Provider                           | Model (`.env`)                                                                                                                      | Endpoint                                                                             |
| ------------------------------------------------------- | ---------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Text generation (lessons, tests, scenarios, evaluation) | Qwen, OpenAI-compatible            | `AI_MODEL=qwen3.8-max`                                                                                                              | `AI_BASE_URL=https://token-plan.ap-southeast-1.maas.aliyuncs.com/compatible-mode/v1` |
| Learner-facing turns                                    | Qwen                               | `AI_FAST_MODEL=qwen3.8-flash`                                                                                                       | same                                                                                 |
| Images                                                  | Qwen / DashScope async task API    | `AI_IMAGE_MODEL=qwen-image-3.0-pro` (fallback `wan2.7-image`)                                                                       | `AI_IMAGE_BASE_URL` = native `/api/v1` root of the token-plan host                   |
| Stored TTS (lesson + test audio)                        | Deepgram Flux batch `/v2/speak`    | `TTS_MODEL=flux-hannah-en`                                                                                                          | api.deepgram.com                                                                     |
| STT for recorded answers                                | Deepgram pre-recorded `/v1/listen` | `nova-3`                                                                                                                            | api.deepgram.com                                                                     |
| Live voice role-play                                    | Deepgram Voice Agent               | listen `flux-general-en` (v2), speak Aura-2 (e.g. `aura-2-thalia-en`) or ElevenLabs, think = Deepgram-managed LLM or Qwen via proxy | `wss://agent.deepgram.com/v1/agent/converse`                                         |

The token-plan key is rejected by `dashscope-intl.aliyuncs.com` (401) — use the
token-plan host for everything. From the Windows host the token-plan host often
fails to connect; from WSL/Docker it is reliable.

## Settings → AI models (2026-09-23)

`/settings/ai-models` (route `ai-models.edit`, gate `manage-ai-models`,
Super Admin only, 403 for everyone else). One `ai_model_settings` row
(`App\Services\Ai\AiModelSettings`) overrides, over `.env`: text model, fast
model, image model, landscape/square image size, STT model, and a
follow-.env / fake / real switch for ai, image, tts, stt. TTS voice and
expressivity are the existing `tts_settings` row. Blank = `.env`. The
provider bindings read the row on every `make()`, so a save applies to the
next job. Keys and base URLs are never stored there (SEC-03).

"Test" queues `RunProviderCheck` (text: one-line JSON; fast: same with the
fast model; image: one square picture; TTS: one sentence; STT: that clip
transcribed), stores `{status, latency_ms, detail|error, checked_at, model,
provider}` in `ai_model_settings.checks`, meters `ai_usages` as
`provider_check`; the page polls while a check is pending/running.

`enable_thinking:false` is still sent to every model on the DashScope host.
Measured live 2026-09-23: `glm-5.3` answers 400 "The value of the
enable_thinking parameter is restricted to True", so the provider resends
once without it on that error. `deepseek-v4-flash`, `kimi-k2.6` and
`MiniMax-M2.5` did not answer within 60 s from WSL (unverified).

## API facts the code relies on

- **Deepgram token grant**: `POST https://api.deepgram.com/v1/auth/grant`,
  `Authorization: Token <key>`, body `{"ttl_seconds": N}` → `{access_token, expires_in}`.
  Browser WebSocket auth: `new WebSocket(url, ['bearer', access_token])`.
- **Voice Agent Settings** (sent by the client as the first message):
  `{type:"Settings", audio:{input:{encoding:"linear16",sample_rate}, output:{encoding:"linear16",sample_rate:24000,container:"none"}}, agent:{language, listen:{provider:{type:"deepgram",version:"v2",model:"flux-general-en"}}, think:{provider:{type,model,temperature}, endpoint?:{url,headers}, prompt}, speak:{provider:{type:"deepgram",model:"aura-2-…"}} | {provider:{type:"eleven_labs",model_id,voice_id}}, greeting}}`.
  Server events: `Welcome, SettingsApplied, ConversationText{role,content}, UserStartedSpeaking, AgentThinking, AgentStartedSpeaking, AgentAudioDone, Error, Warning`; binary frames = agent audio.
  Client events: `KeepAlive, UpdatePrompt, UpdateSpeak, InjectAgentMessage, InjectUserMessage`.
- Flux TTS voices are **not** valid agent speak models; use Aura-2 or ElevenLabs.
- A custom think endpoint must be public HTTPS and its headers travel from the
  browser. **Never put the Qwen key in Settings.** Qwen as the agent's brain goes
  through `/voice-agent/llm/{signed-token}/chat/completions` on our server, which
  only works when `APP_URL` is public HTTPS. Default think = Deepgram-managed
  `open_ai` / `gpt-4o-mini` (billed by Deepgram, no key in the browser).
- **DashScope image task**: `POST {base}/services/aigc/text2image/image-synthesis`
  with `X-DashScope-Async: enable`, body `{model, input:{prompt}, parameters:{size:"1328*1328"|"1664*928", n:1, watermark:false, prompt_extend:true}}`
  → `output.task_id`; poll `GET {base}/tasks/{id}` until `output.task_status` is
  `SUCCEEDED` (`output.results[0].url`) or `FAILED`. URLs expire in 24 h —
  download immediately into `media_assets` (public disk, UUID name).
- **Deepgram STT**: `POST /v1/listen?model=nova-3&smart_format=true`, raw audio
  body with its Content-Type → `results.channels[0].alternatives[0].transcript`.

## Decisions

1. Everything AI-generated lands as a **draft** (GEN-03) with Regenerate (GEN-04).
   A generated lesson is created with `status=draft`; images and audio attach to it.
2. Every provider call runs in a queued job with a status on the owning row
   (PERF-04) and is metered in `ai_usages` before dispatch (AIL-01..04).
3. The live voice call never records video. The camera preview is local only
   (PRIV-03); only the text transcript is stored (RP-11), turn by turn.
4. One voice call = one role-play attempt (RP-06); admin test calls set
   `is_preview` (RP-13).
5. Learners see content only when it is `published`, for their department, and
   shared (`hotel_id IS NULL`) or their hotel's (ORG-04, CMS-04, JOURNEY-03).
