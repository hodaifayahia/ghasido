# Sending email from contact@ghasido.com (Hostinger)

Client request 2026-09-29: real emails to Gmail, Outlook/Hotmail and any other
inbox, sent from the Hostinger mailbox **contact@ghasido.com**, in the branded
GHASIDO layout.

Everything is set in the app, not in `.env`: **Settings → Email** (Super Admin
only). Until that page is saved, `.env` (`MAIL_MAILER=log`) applies unchanged.

## 1. What to enter on Settings → Email

| Field                   | Value                                                                                                                            |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| How emails are sent     | **Send real emails (SMTP)**                                                                                                      |
| Send emails immediately | **On** (keep it on until the queue cron jobs in `hostinger.md` exist)                                                            |
| SMTP host               | `smtp.hostinger.com`                                                                                                             |
| Port / Encryption       | `465` + **SSL** (or `587` + **TLS / STARTTLS**)                                                                                  |
| Username                | `contact@ghasido.com` (the full address)                                                                                         |
| Password                | the mailbox password set in hPanel → Emails (stored encrypted; leave blank later to keep it)                                     |
| From address            | `contact@ghasido.com`: **must be the same as the username**, or Hostinger refuses the mail and Gmail/Outlook treat it as spoofed |
| From name               | `GHASIDO`                                                                                                                        |
| Reply-To                | optional; blank = replies go to contact@ghasido.com                                                                              |

Then **Send a test email** to a Gmail and an Outlook/Hotmail address. The page
shows the mail server's answer in plain words (wrong password, connection
refused, wrong port/encryption…), and each test is in the audit log.

"Send emails immediately" delivers every email inside the request that sends
it, so no queue worker is needed. If the mail server fails, the page action
still succeeds: the email is logged and put on the database queue instead
(reminders are marked **failed** in the Messages log). AI, audio and export
jobs always stay on the queue. Reminders scheduled for later need the
scheduler cron (`hostinger.md`).

## 2. DNS records for ghasido.com (hPanel)

Gmail and Outlook only put mail in the inbox when the domain proves the
server may send for it. In hPanel open **Emails → ghasido.com → DNS settings**
(or **Domains → ghasido.com → DNS / Nameservers**) and check these records.
If the domain uses Hostinger nameservers, hPanel offers to add them with one
click.

| Type  | Name                         | Value                                                               | Why                                                                                                            |
| ----- | ---------------------------- | ------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| MX    | `@`                          | `mx1.hostinger.com` (priority 5), `mx2.hostinger.com` (priority 10) | receiving mail for contact@                                                                                    |
| TXT   | `@`                          | `v=spf1 include:_spf.mail.hostinger.com ~all`                       | SPF: Hostinger may send for ghasido.com. Only **one** SPF record may exist: merge any other `include:` into it |
| CNAME | `hostingermail-a._domainkey` | `hostingermail-a.dkim.mail.hostinger.com`                           | DKIM signing (enable it in hPanel → Emails → DKIM; Hostinger shows the exact names, usually `-a`, `-b`, `-c`)  |
| CNAME | `hostingermail-b._domainkey` | `hostingermail-b.dkim.mail.hostinger.com`                           | DKIM                                                                                                           |
| CNAME | `hostingermail-c._domainkey` | `hostingermail-c.dkim.mail.hostinger.com`                           | DKIM                                                                                                           |
| TXT   | `_dmarc`                     | `v=DMARC1; p=none; rua=mailto:contact@ghasido.com`                  | DMARC, report only to start. After 2–4 weeks of clean reports, move to `p=quarantine`                          |

If hPanel shows different DKIM names or values, use hPanel's: they are
generated per account. DNS changes take from a few minutes to 24 hours.

## 3. Check deliverability

1. Open <https://www.mail-tester.com>, copy the address it shows.
2. On Settings → Email, **Send a test email** to that address.
3. Click "Then check your score". Aim for **9/10 or more**. It lists what is
   missing (SPF, DKIM, DMARC, blacklists).
4. Also send a test to a Gmail inbox and open **⋮ → Show original**:
   `SPF: PASS`, `DKIM: PASS`, `DMARC: PASS` must all show.

## 4. What the app does for deliverability

- From = the authenticated mailbox (validated on save).
- Every email has an HTML part and a plain-text part, a real subject, a
  preheader, Reply-To, and images by absolute URL (`APP_URL/brand/email/…`).
  **`APP_URL` must be `https://ghasido.com`** (or the live domain) or the logo
  will not load.
- Reminder emails carry `List-Unsubscribe` (the profile page, where the
  employee withdraws reminder consent, plus a mailto).
- One layout for all emails, Laravel's password reset included:
  `resources/views/mail/layout.blade.php` (600px tables with inline styles,
  bulletproof Outlook button, RTL + Arabic font stack when the recipient's
  language is Arabic).

## Code map

- `app/Services/Mail/MailSettings.php`: stored row → `config('mail.*')`;
  applied when the mail manager is first resolved and before every queued job
  (`app/Providers/MailSettingsServiceProvider.php`).
- `app/Services/Mail/MailDelivery.php` + `app/Mail/BrandedMailable.php`:
  "send immediately" (sync connection for mail only, fallback to the queue).
- `app/Http/Controllers/Settings/MailSettingsController.php`, page
  `resources/js/pages/settings/Email.vue`.
