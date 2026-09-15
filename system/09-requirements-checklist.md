# 09 — Requirements Checklist

A flat list for tracking build progress. Tick items as they are delivered. Details for each ID are in the linked documents.

## Structure & accounts (01)

- [ ] ORG-01 Multi-hotel, multi-department, multi-employee structure
- [ ] ORG-02 Departments configurable, not hard-coded
- [ ] ORG-03 Employee bound to one hotel + one department
- [ ] ORG-04 Content assigned per department (optionally per hotel)
- [ ] ROLE-01 Three roles with server-side permission enforcement
- [ ] ROLE-02 No cross-department/hotel access, including by direct URL or API
- [ ] ROLE-03 Super Admin overrides hotel-level permissions
- [ ] ROLE-04 Full AI transcripts restricted to Super Admin
- [ ] AUTH-01 Login with username + password (not email)
- [ ] AUTH-02 No public self-registration
- [ ] AUTH-03 Creator sets username + initial password
- [ ] AUTH-04 First login asks for email + reminder consent
- [ ] AUTH-05 Email can be made mandatory per hotel/cohort
- [ ] AUTH-06 Employee can update email and consent later
- [ ] AUTH-07 Password change and admin password reset
- [ ] AUTH-08 Accounts deactivatable, data preserved
- [ ] AUTH-09 Cross-device session continuity
- [ ] AUTH-10 Hashing, rate limiting, session expiry
- [ ] SUB-01 Per-hotel, per-department seat quotas set by Super Admin
- [ ] SUB-02 Manager creates accounts only within quota
- [ ] SUB-03 Quotas adjustable at any time
- [ ] SUB-04 Contract period per hotel (e.g. 60 days)
- [ ] SUB-05 Access blocked after period ends, data kept
- [ ] SUB-06 Extend / pause / reactivate period
- [ ] SUB-07 Days remaining visible with warning state
- [ ] SUB-08 Hotel deactivation cascades to its accounts
- [ ] API-01 Client owns API accounts, keys and billing
- [ ] API-02 Keys stored server-side only
- [ ] API-03 Usage visible in admin panel
- [ ] API-04 Provider/keys configurable without code change

## Employee experience (02)

- [ ] JOURNEY-01 Pre-test gates access to lessons
- [ ] JOURNEY-02 Home screen with progress + Continue
- [ ] JOURNEY-03 Only own department's content visible
- [ ] JOURNEY-04 Post-test unlock condition
- [ ] JOURNEY-05 Certificate after completion
- [ ] LESSON-01 Each part on its own step/page
- [ ] LESSON-02 Admin chooses which parts each lesson has
- [ ] LESSON-03 Step indicator / progress bar
- [ ] LESSON-04 Next/Back and revisit completed steps
- [ ] LESSON-05 Lesson Complete screen
- [ ] LESSON-06 Image + audio + short text approach
- [ ] CTRL-01 English visible by default
- [ ] CTRL-02 Arabic only via Show Meaning, hideable
- [ ] CTRL-03 Show Meaning available on essentially any text element
- [ ] CTRL-04 Show Meaning disabled during tests
- [ ] CTRL-05 Audio pre-generated and stored, not regenerated
- [ ] CTRL-06 Large mobile tap targets for audio controls
- [ ] PHRASE-01 My Phrasebook per employee
- [ ] PHRASE-02 Save from anywhere via ⭐
- [ ] PHRASE-03 Play normal/slow, show meaning, remove
- [ ] PHRASE-04 Grouping / search
- [ ] PHRASE-05 Persists across devices
- [ ] PHRASE-06 (Suggested) Review mode from Phrasebook
- [ ] PROG-01 Resume exactly where left off, any device
- [ ] PROG-02 Full list of saved state elements
- [ ] PROG-03 Continuous autosave
- [ ] PROG-04 Graceful recovery on connection loss
- [ ] PROG-05 "Continue where you left off" entry point
- [ ] PROG-06 Only Super Admin can reset progress, logged

## Practice & assessment (03)

- [ ] PRAC-01 Admin picks activity type per lesson/department need
- [ ] PRAC-02 Short, simple, image/audio-based
- [ ] PRAC-03 Simple feedback after answering
- [ ] PRAC-04 Answers and results saved
- [ ] PRAC-05 All activity types also usable in Pre/Post tests
- [ ] PRAC-06 Optional images, audio, Show Meaning on instructions
- [ ] PRAC-07 Configurable attempts in practice
- [ ] Activity types: Listen & Choose, Look & Listen, Best Response, Listen & Match, Watch & Respond, Words & Sentences, Put the Dialogue in Order, Email Activity, Phone Activity, Record & Compare
- [ ] WRITE-01 Scenario + given information + answer box
- [ ] WRITE-02 AI evaluation + better-reply suggestion
- [ ] WRITE-03 Graded difficulty: complete/order → short reply → full email
- [ ] WRITE-04 Verbatim answer + evaluation stored
- [ ] WRITE-05 Writing tasks usable in lessons, practice, Pre and Post tests
- [ ] WRITE-06 Available to any department
- [ ] TEST-01 Pre-test before lessons, Post-test after
- [ ] TEST-02 Same template/skills/difficulty, different questions
- [ ] TEST-03 No Show Meaning, no answers/feedback during the test
- [ ] TEST-04 Result visibility configurable
- [ ] TEST-05 Full question-type library in the test builder
- [ ] Question types: Listening + Choose Picture, Listen & Best Response, Situation + Choose, Short Reading, Complete Sentence, What Would You Say?, Writing
- [ ] TEST-06 Actual answers stored question by question
- [ ] TEST-07 Audio recordings stored and playable in admin
- [ ] TEST-08 Written answers stored with AI evaluation
- [ ] TEST-09 Per-attempt metadata stored
- [ ] TEST-10 Exportable per employee/department/hotel
- [ ] TIME-01 Optional time limit on exercises, questions or whole tests
- [ ] TIME-02 Timer off by default
- [ ] TIME-03 Countdown + auto-submit behaviour
- [ ] TIME-04 Time taken recorded even without a limit
- [ ] TIME-05 Timer survives refresh (server-side)
- [ ] CERT-01 Downloadable PDF certificate
- [ ] CERT-02 Participation and Completion types
- [ ] CERT-03 Required certificate fields
- [ ] CERT-04 Admin-defined completion condition
- [ ] CERT-05 Editable template
- [ ] CERT-06 Issued certificates listed, revocable
- [ ] CERT-07 (Suggested) Verification ID

## AI (04)

- [ ] RP-01 Scenario list per department
- [ ] RP-02 Get Ready screen in English with Show Meaning
- [ ] RP-03 Turn-by-turn conversation, text and/or voice
- [ ] RP-04 AI stays in role — not a general chatbot
- [ ] RP-05 3 attempts per scenario (configurable)
- [ ] RP-06 One attempt = one full short conversation
- [ ] RP-07 Feedback after the conversation
- [ ] RP-08 Evaluation focused on communicative success, not harsh on small grammar
- [ ] RP-09 Encouraging, adult-appropriate tone
- [ ] RP-10 Save key phrase / better expression to Phrasebook
- [ ] RP-11 Transcript, attempt, feedback and scores stored
- [ ] RP-12 Admin creates/edits/publishes scenarios without code
- [ ] RP-13 Admin can test a scenario before publishing
- [ ] RP-14 Scenario usable inside a lesson or standalone
- [ ] AIL-01 AI usage limit per employee
- [ ] AIL-02 Limits per hotel
- [ ] AIL-03 Clear message at limit, rest of platform unaffected
- [ ] AIL-04 Usage visible in admin
- [ ] AIE-01 AI evaluation of written answers + better version
- [ ] AIE-02 AI evaluation of spoken answers with transcript
- [ ] AIE-03 Configurable criteria and weighting
- [ ] AIE-04 Structured, exportable evaluation output
- [ ] AIE-05 Admin can override an AI score
- [ ] GEN-01 Manual vs Generate with AI always available
- [ ] GEN-02 AI proposes Arabic meaning + simple explanation + hotel example
- [ ] GEN-03 Nothing published automatically — review before Save/Publish
- [ ] GEN-04 Regenerate option
- [ ] GEN-05 Available across vocabulary, expressions, examples, dialogue, instructions
- [ ] GEN-06 (Suggested) Bulk generation
- [ ] TTS-01 Generate Audio Normal + Slow
- [ ] TTS-02 Audio stored as files and reused
- [ ] TTS-03 Preview, regenerate, or upload own recording
- [ ] TTS-04 Configurable, consistent voice
- [ ] TTS-05 Mobile-optimised files
- [ ] TTS-06 Missing-audio flagging

## Admin & CMS (05)

- [ ] ADM-01 Dashboard overview with drill-down
- [ ] ADM-02 No developer needed for management actions
- [ ] ADM-03 Admin action logging
- [ ] Manager dashboard scoped to own hotel, with reminders, without content editing
- [ ] CMS-01 Manage Courses/Units and Lessons
- [ ] CMS-02 Add and arrange all block types in a lesson
- [ ] CMS-03 Draft/Published + Preview as employee
- [ ] CMS-04 Department-global or hotel-scoped content
- [ ] CMS-05 Lesson settings (cover, duration, prerequisites, completion, visibility)
- [ ] CMS-06 Reusable content library
- [ ] BLD-01 Drag & drop builder
- [ ] BLD-02 Block types: Text, Image, Vocabulary, Expressions, Audio, Video, Dialogue, Practice, Email Activity, Phone Activity, AI Role-play, Note
- [ ] BLD-03 Reorder, edit, delete, duplicate, hide blocks
- [ ] BLD-04 Section layouts: Full width, Image+Text, Text+Image, Cards
- [ ] BLD-05 Add and position images and decorative elements
- [ ] BLD-06 Output always responsive
- [ ] BLD-07 Blocks map to employee step pages
- [ ] BLD-08 Autosave / undo
- [ ] BLD-09 (Suggested) Lesson templates
- [ ] MED-01 Admin controls all imagery incl. icons, badges, decorative elements
- [ ] MED-02 Upload / Choose / Replace / Remove per slot
- [ ] MED-03 Central searchable media library
- [ ] MED-04 Automatic resize/compression
- [ ] MED-05 Images, audio, video supported
- [ ] MED-06 Logo and brand identity managed separately
- [ ] MED-07 Alt text per image
- [ ] TSTM-01 Test builder uses the full question library
- [ ] TSTM-02 Per-test settings (order, timing, pass condition, visibility)
- [ ] TSTM-03 Pre/Post pairing
- [ ] TSTM-04 AI scenario management with preview
- [ ] TSTM-05 Assign tests/scenarios to department, course or hotel
- [ ] REM-01 Email + consent status visible in admin
- [ ] REM-02 Send reminder to one employee or a group
- [ ] REM-03 Automatic reminders for inactivity period
- [ ] REM-04 Editable templates with variables
- [ ] REM-05 Consent respected and recorded
- [ ] REM-06 Reminder log
- [ ] REM-07 Manager can remind own employees only
- [ ] REM-08 (Suggested) In-app notifications

## Data & reporting (06)

- [ ] DATA-01 Actual Pre/Post answers per question
- [ ] DATA-02 Audio recordings stored and playable
- [ ] DATA-03 Written answers + AI evaluation
- [ ] DATA-04 Exercise results and answers
- [ ] DATA-05 Full AI role-play conversations, attempts, feedback
- [ ] DATA-06 Lessons completed, progress, last activity
- [ ] DATA-07 Training start and completion dates
- [ ] DATA-08 Time taken per item
- [ ] DATA-09 Phrasebook contents
- [ ] DATA-10 Data retained after deactivation/contract end
- [ ] DATA-11 Content versioning for answered items
- [ ] REP-01 Filter by hotel / department / employee / date
- [ ] REP-02 Detailed drill-down view
- [ ] REP-03 Excel / CSV export
- [ ] REP-04 Full set of exportable datasets
- [ ] REP-05 Audio bundle export with traceable filenames
- [ ] REP-06 Long-format (one row per answer) export
- [ ] REP-07 Anonymised export option
- [ ] REP-08 Manager export scoped and limited
- [ ] PRIV-01 to PRIV-05 Privacy, consent, access control, HTTPS, backups

## Design & technical (07)

- [ ] UX-01 to UX-08 Colours, animation, adult tone, simple navigation, mockups as the pixel-exact specification (UX-08 superseded 2026-09-15 — `AGENTS.md` §0)
- [ ] RESP-01 App-like mobile experience (primary device)
- [ ] RESP-02 Admin/builder usable on desktop
- [ ] RESP-03 Builder output responsive
- [ ] RESP-04 Mobile-friendly media players
- [ ] RESP-05 Mobile browser voice recording
- [ ] RESP-06 (Suggested) PWA install
- [ ] I18N-01 to I18N-03 English content, switchable interface language, correct Arabic rendering/RTL
- [ ] PERF-01 to PERF-04 Fast on mobile data, lazy loading, connection tolerance, AI loading states
- [ ] SEC-01 to SEC-06 Server-side authorisation, hashing, secrets, upload validation, HTTPS/backups, audit log
- [ ] ACC-01 to ACC-05 Readable type, contrast, non-colour-only feedback, tap targets, alt text
- [ ] Delivery: staging environment, seed data, admin user guide, full handover
