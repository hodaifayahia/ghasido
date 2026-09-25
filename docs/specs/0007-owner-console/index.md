# 0007 — Owner console: API keys, credit and prices

_Requested by the platform owner (clickDz) on 2026-09-25. Requirement IDs: API-02, API-03, API-04, AIL-03, AIL-04, SEC-01, SEC-03, SEC-06._

## Why

GHASIDO runs on two paid APIs the platform owner pays for: **Qwen** (Alibaba Model Studio token plan: text AI and images) and **Deepgram** (lesson audio, transcription, pronunciation checks, live voice calls). The client (the Super Admin) uses them through the app. The owner wants to:

1. change the API keys without editing `.env` or redeploying;
2. give the client a budget per API ("$200 of Deepgram", "250,000 Qwen tokens");
3. see what is spent and what is left;
4. have the app stop calling an API when its budget is spent, and start again when the owner recharges.

## Decisions

| # | Decision | Why |
| - | -------- | --- |
| D1 | **The owner is not a `User`.** Separate `owners` table, `owner` session guard, own login at `/owner/login`. Accounts are created from the server with `php artisan owner:create`; there is no sign-up and no e-mail reset. | The Super Admin passes every Gate check (`Gate::before`) and manages users, so any owner flag on `users` could be granted, reset or bypassed by the client. A separate guard cannot be reached from the app at all. |
| D2 | **Keys live in `api_account_settings.api_key`, encrypted with `APP_KEY`** (Eloquent `encrypted` cast). A stored key overrides `.env`; clearing it falls back to `.env`. The browser only ever sees `••••` + the last four characters. | API-02/SEC-03 still hold: keys stay server-side. The owner asked to change them from a page. |
| D3 | Keys are read at resolve time through `ApiKeyring::key('<config key>')`, never copied into `config()` at boot. | Queue workers are long-running; the provider bindings are plain `bind()`, so a key saved now is used by the next job without restarting workers. |
| D4 | **Credit is a ledger** (`api_credit_topups`): each recharge adds dollars and, for Qwen, tokens. Negative rows correct a mistake. | A running total that is overwritten cannot be audited; a ledger can. |
| D5 | **Spend is summed live from `ai_usages`** for the account's providers since `metering_started_at` (set on the first recharge). Unpriced rows are costed at the current price, as the AI usage report already does. | One source of truth. Usage from before the first recharge is not charged against it. |
| D6 | **No recharge yet = no limit.** A limit applies to dollars once any dollar recharge exists, to tokens once any token recharge exists; the account is blocked when either runs out, or when the owner pauses it. | A deploy must not switch AI off before the owner has set anything. |
| D7 | **Enforced twice.** (a) Before dispatch, with a user-facing message: role-play (text and voice), lesson and image generation (`AiLimitReached::forCredit`). (b) Hard stop in the provider bindings: resolving the Qwen or Deepgram provider throws when its account is blocked, so no queued job can spend. The live voice call and its Qwen proxy check too. | (a) gives a clean message; (b) guarantees nothing slips through. Calls already running finish, so spend can end slightly over the cap. |
| D8 | **Prices move to the owner.** The price table (`ai_model_prices`) is edited only on the owner console; Settings → AI usage shows costs but no editor. | Whoever sets prices controls the dollar budget. |
| D9 | **Deepgram and images are metered in real units.** Units `seconds` (transcription, pronunciation listens, live voice calls: one `voice_call` row per call at hang-up) and `images` (one per generated image) join `tokens` and `characters`. Prices are still stored per million units; the console shows them per minute and per image. A model id ending in `*` prices every model with that prefix (`aura-2-*`). | Before this, only TTS had a Deepgram cost, so a Deepgram dollar budget would never move. |

| D10 | **A recharge is a pack: dollars + provider units** (added 2026-09-25 at the owner's request). Qwen counts **tokens**; Deepgram counts **audio minutes** (transcription, pronunciation checks, live voice calls; stored as seconds) and **speech characters** (TTS). Once an account has units, the units are the limit and the dollars are what the Super Admin paid: her dollars left = dollars credited × the share left of the unit nearest empty. A dollar-only recharge on a pack account is refused. An account with dollars only is still spent at the owner's prices. Each usage row counts against the meter of `AiFeature::unit()`. | The owner sells "$200 = 250,000 tokens"; the provider's real cost is his business. Qwen images are not tokens, so a Qwen pack does not count them (the per-admin daily image limit still applies). |
| D11 | **The Super Admin sees her credit** (read-only) in an "AI credit" card on her dashboard (below the stat cards) and on Settings → AI usage: per service, dollars left of what she was credited, units left, state and a "ask the platform owner to recharge" line when low or out. `CreditSummary::forSuperAdmin()`; never the owner's cost, prices or keys; hotel admins and managers never receive it. | She asked to see "how many dollars she has". |

| D12 | **Low-credit emails.** When an account drops under 20% left (the "Running low" state everywhere), and again when it is used up, `CreditAlerts` queues `ApiCreditAlertMail` to every owner and every active Super Admin, checked after each metered call (`UsageMeter::record`). Each is sent once (claimed with a conditional UPDATE on `api_account_settings.low_alert_sent_at` / `empty_alert_sent_at`); a recharge clears both. A problem in the check is logged and never breaks metering. | Learners in the research study must not be cut off mid-lesson without warning. Needs a real mailer: production has `MAIL_MAILER=log` until SMTP is set. |
| D13 | **Admin contact profile** (owner request 2026-09-25). Super Admin and hotel Admin accounts must fill first name, last name, email, phone and address; `EnsureAdminProfileCompleted` (web group) sends their page visits to Settings → Profile until done (settings, sign-out and the owner console stay open). The display `name` follows first + last. Managers and employees keep name + email only: no phone or address for learners (PRIV-03). | The platform must be able to reach its admins, e.g. for D12. |

## Data

- `owners`: name, email (unique), password (hashed), remember_token, last_login_at.
- `api_account_settings`: account (`qwen`/`deepgram`, unique), api_key (encrypted, nullable), key_updated_at, paused_at, metering_started_at.
- `api_credit_topups`: account, amount_usd decimal(12,4), amount_tokens, amount_characters, amount_seconds (bigint; the last two from migration `2026_09_25_100002`), note, owner_id, created_at.
- `ai_usages`: new index (provider, occurred_at).

Provider mapping (`App\Enums\ApiAccount`): Qwen = `ai_usages.provider` `qwen`, `qwen_*`, `dashscope`; Deepgram = `deepgram`, `deepgram_*`.

Which config key a stored key replaces:

| Config key | Account | Only when |
| ---------- | ------- | --------- |
| `services.ai.key` | Qwen | `AI_PROVIDER` is `qwen` or `fake` |
| `services.ai.image_key` | Qwen | `AI_IMAGE_PROVIDER` is `qwen`, `dashscope` or `fake` |
| `services.tts.key` | Deepgram | `TTS_PROVIDER` is `deepgram` or `fake` |
| `services.stt.key` | Deepgram | `STT_PROVIDER` is `deepgram` or `fake` |
| `services.voice_agent.key` | Deepgram | always |

## Routes (`routes/owner.php`)

| Method | URL | Name |
| ------ | --- | ---- |
| GET/POST | `/owner/login` | `owner.login`, `owner.login.store` (5 tries/min per e-mail + IP) |
| POST | `/owner/logout` | `owner.logout` |
| GET | `/owner` | `owner.dashboard` |
| PUT/DELETE | `/owner/accounts/{account}/key` | `owner.accounts.key.update` / `.destroy` |
| POST | `/owner/accounts/{account}/topups` | `owner.accounts.topups.store` |
| PATCH | `/owner/accounts/{account}/pause` | `owner.accounts.pause` |
| POST | `/owner/accounts/{account}/check` | `owner.accounts.check` (queues the existing `RunProviderCheck`) |
| POST | `/owner/accounts/deepgram/balance` | `owner.accounts.balance` (live Deepgram project balance) |
| PUT | `/owner/prices` | `owner.prices.update` |

All but login sit behind the `owner` middleware (`EnsureOwnerAuthenticated`). The Super Admin's session is on the `web` guard and does not pass it.

## Operations

```bash
php artisan migrate
php artisan owner:create you@example.com --name="Your name"   # prompts for a password (12+ chars)
```

Run `owner:create` again with the same e-mail to reset the password. The console is at `/owner`.

After the first deploy, set prices for every model the app uses (the console lists unpriced models), then recharge. Until a recharge exists an account has no limit.

## Where it lives

- Backend: `app/Enums/ApiAccount.php`, `app/Services/Owner/` (`ApiKeyring`, `ApiCredit`, `CreditBalance`, `DeepgramBalance`, `OwnerConsole`), `app/Http/Controllers/Owner/`, `app/Http/Requests/Owner/`, `app/Http/Middleware/EnsureOwnerAuthenticated.php`, `app/Console/Commands/CreateOwner.php`, `routes/owner.php`.
- Enforcement: provider bindings in `AppServiceProvider`, `UsageMeter::assertWithinLimits()` / `assertVoiceCredit()` / `recordVoiceCall()`, `LessonGenerator::assertWithinLimits()`, `VoiceCallService`, `VoiceAgentLlmController`.
- Frontend: `layouts/OwnerLayout.vue`, `pages/owner/{Login,Dashboard}.vue`, `components/owner/`.
- Tests: `tests/Feature/Owner/` (auth, console actions, credit and enforcement).
- Side effect: with a second guard, Larastan types a bare `$request->user()` as `Owner|User`; the three call sites that needed it now name `'web'` (and `HandleInertiaRequests` does too, so an owner session never becomes `auth.user`).

## Verified (2026-09-25)

- `php artisan test`: 864 passed, 10 skipped (Fortify feature flags); `tests/Feature/Owner`: 43 passed. PHPStan and Pint clean on every changed file; `vp check` and `vue-tsc` clean.
- Browser (SQLite harness on :8097, fake AI): `/owner/login` and `/owner` at 1280×853, 1240×698 and 390×844, with no horizontal overflow and no console errors. A recharge through the form works; a signed-in Super Admin is sent to `/owner/login`; Settings → AI usage shows no price editor. At 1240 the two account cards stack (the two-column grid starts at `xl`).
- Fixed after the first deploy (2026-09-25): the owner sign-in used Laravel's shared `url.intended`, so a browser that had opened an app page while signed out was sent there, then to the app login. The owner side now keeps its own key (`EnsureOwnerAuthenticated::INTENDED`) and only returns to `/owner…` URLs; covered by three tests in `OwnerAuthTest` and re-checked live in a browser.
- Not verified live: real Qwen/Deepgram calls through a stored key, and the Deepgram balance endpoint with a real key (covered by HTTP-faked tests only).

## Not done (follow-ups)

- No e-mail to the owner or banner for the Super Admin when credit runs low; the console shows a "Low" state at under 10% left.
- Qwen's real account balance is not read: Alibaba's billing API needs a signed AccessKey, not the model key. Deepgram's live balance is shown on demand.
- No 2FA on the owner login.
