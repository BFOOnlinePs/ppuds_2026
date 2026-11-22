# Task brief for an AI coding assistant — PPUDS mobile sign-in (Flutter)

> **For the Flutter developer:** give this whole file to your AI assistant together with
> `ppuds_auth_api.dart` (same folder). The human-friendly Arabic guide with more
> examples is `docs/mobile-authentication-api.md`.

---

## 1. Context

You are working on the **PPU Dual Studies (PPUDS)** Flutter app. The backend (Laravel) already exposes all the endpoints below; **do not invent endpoints or fields**.

The app already has:
- a Dio client with the server `baseUrl`;
- token storage, and a user model built from the `user` object of `POST /api/v1/auth/login`;
- the **university** sign-in (`/api/v1/auth/university-login`, Keycloak), which is **out of scope** — do not touch it.

Which system is active is read from `GET /api/v1/ppuds/settings` → `data.login_method`:
- `2` (university SSO): keep the existing university sign-in, and **do nothing from this brief**.
- `1` (internal): implement this brief.

## 2. Goal

Implement the **internal** sign-in so that **the dashboard decides how each person signs in**, and the app adapts at runtime:

| Mode (`mode`) | User flow |
|---|---|
| `password` | email + password |
| `code` | email → one-time code (SMS or email) → signed in. **No password.** |
| `password_or_code` | the user picks password or code |
| `password_then_code` | email + password → one-time code → signed in (two-step) |

The methods can differ **per role and per user**. When `options.per_user == true`, ask for the email first and call `identify` to learn that person's mode.

Plus **QR sign-in** (mobile only):
- **Personal QR** (one per company supervisor): scan → **signed in immediately**. No email, no code.
- **Company printed QR**: scan → show the company → email → code → signed in.

## 3. Use the provided API file

`ppuds_auth_api.dart` is the complete, typed API layer. **Use it as is.** Don't rewrite the HTTP calls.

- Classes: `PpudsAuthApi`, `AuthOptions`, `CodeChallenge`, `AuthSession`, `QrCompany`, `PasswordLoginResult` (`PasswordSignedIn` | `PasswordNeedsCode`), `QrScanResult` (`QrSignedIn` | `QrNeedsCode`), `PpudsApiException`.
- Requirements: Dart 3 and `dio`.
- Every successful sign-in returns `AuthSession(token, tokenType, user)`. **Feed it into the app's existing "signed in" handling**, the same as the current password login: save the token, build the user, go home.

## 4. Screen logic (state machine)

```
[Start] → GET options()
   │
   ├─ options.showScanQr → show "Scan QR code" button  ──► [Scan]
   │
   ├─ options.perUser == false → build screen from options.mode
   └─ options.perUser == true  → [Email step] → identify(email) → build screen from that mode

Build screen from mode:
   password            → [Password step]
   code                → requestCode(email) → [Code step]
   password_or_code    → [Password step] + button "Sign in with a code instead" → requestCode(email) → [Code step]
   password_then_code  → [Password step]

[Password step] → loginWithPassword(email, password)
   PasswordSignedIn(session)      → signed in ✔
   PasswordNeedsCode(challenge)   → [Code step] with challenge.loginTicket
   401                            → "wrong email or password" (use message)
   403                            → show message ("your account signs in with a code"),
                                    then requestCode(email) → [Code step]

[Code step] (fields: code input of challenge.codeLength digits, resend countdown = challenge.resendAfter)
   submit → verifyCode(code, email | loginTicket [, qrToken])
            ok → signed in ✔
            422 → show message, keep the screen (code wrong/expired)
            429 → show message, disable submit for retryAfter seconds
            401 (ticket) → back to [Password step]
   resend (when countdown hits 0) → requestCode(email [, qrToken]) or requestCode(loginTicket)

[Scan] → scanQr(scannedText, deviceName, fcmToken)
   QrSignedIn(session)            → signed in ✔ (no further input!)
   QrNeedsCode(company, qrToken)  → show company name + logo → [Email step for QR]
                                    → requestCode(email, qrToken) → [Code step] (verify with email + qrToken)
   404 → "This QR code is not valid" (message) → allow rescanning
```

Channel choice: if `channelChoice == true`, show a "Send by: Email / SMS" selector (default `defaultChannel`) and pass `channel` to `requestCode` / `loginWithPassword`. Otherwise, don't show it.

## 5. Hard rules (security and privacy)

1. **Never say an account does not exist.**
   - `identify` and `requestCode(email)` answer the same for unknown emails.
   - After `requestCode`, show the server `message` ("If this account can sign in this way, a code has been sent"), not "Code sent to your email".
2. **Never store** the one-time code, the `loginTicket` or the scanned personal QR text in persistent storage, logs, analytics or crash reports. Keep them only in the screen state.
3. **Never decide the mode on the client.** Always ask `options()` / `identify()` each time the sign-in screen opens; the dashboard can change modes at any time.
4. **Do not retry automatically** on 401/403/404/422/429. On 429, honour `retryAfter`.
5. **Send headers:** `Accept: application/json` and `Accept-Language` (`ar` or `en`). Show `message` from errors as is; it's already translated.
6. **Pass the scanned QR text untouched.** It's a URL like `https://<domain>/c/<43 chars>`; the server extracts the token.
7. Always send `device_name` and the FCM token (`fcm_token`) on the final sign-in call (`loginWithPassword`, `verifyCode`, `scanQr`), like the current login does.

## 6. UX requirements

- **Code input:**
  - numeric keyboard, `codeLength` boxes, `autofillHints: [AutofillHints.oneTimeCode]`;
  - auto-submit when complete;
  - support Arabic (RTL) layout, but keep the digits left-to-right.
- **Resend button:** disabled with a visible countdown (`resendAfter` seconds); after `429`, count down `retryAfter`.
- **Show the validity** ("valid for N minutes" from `expiresIn`).
- **"Change email"** on the code step returns to the email step and discards any `loginTicket`.
- **Loading states** on every button; disable double submits.
- **QR scanner:**
  - Use the app's existing scanner package. If there is none, use `mobile_scanner`.
  - Ask for camera permission, with a clear message if it's denied.
- **After a personal QR scan,** go straight to home, with no extra screens.

## 7. Endpoint contract (summary)

All bodies are JSON and responses are `{ "status": bool, "message": string, "data": object|null }`.

| Call | Request body | `data` on 200 |
|---|---|---|
| `GET /api/v1/auth/options` | — | `mode, per_user, password_login, code_login, two_step, company_qr_login, personal_qr_login, channels[], default_channel, channel_choice, code_length` |
| `POST /api/v1/auth/identify` | `email` | `mode, password_login, code_login, two_step, channels[], default_channel, channel_choice, code_length` |
| `POST /api/v1/auth/login` | `email, password, channel?, device_name?, fcm_token?` | `token, token_type, user` **or** `requires_code: true, login_ticket, channel, expires_in, resend_after, code_length` |
| `POST /api/v1/auth/otp/request` | `email` \| `email + qr_token` \| `login_ticket`; `channel?` | `channel, expires_in, resend_after, code_length` |
| `POST /api/v1/auth/otp/verify` | `code` + (`email` \| `email + qr_token` \| `login_ticket`); `device_name?, fcm_token?` | `token, token_type, user` |
| `POST /api/v1/auth/qr/scan` | `qr_token, device_name?, fcm_token?` | `type: "personal", token, token_type, user` **or** `type: "company", company: {id, name, logo_url}` |
| `POST /api/v1/auth/logout` | — (Bearer) | `null` |

Errors: `401, 403, 404, 422` (field errors in `data`), and `429` (`data.retry_after` plus the `Retry-After` header). See `PpudsApiException` in the Dart file.

## 8. Deliverables

1. `ppuds_auth_api.dart` placed in the data layer and wired to the app's Dio.
2. A sign-in state holder (Bloc / Cubit / Riverpod / Provider — **whatever the app already uses**) implementing section 4.
3. Screens or widgets:
   - email step;
   - password step, with an optional "use a code instead" button;
   - code step (input, countdown, resend, change email);
   - QR scan screen;
   - company confirmation, for the printed company QR.
4. Arabic and English strings for every new label, using the app's existing localization system.
5. Unit tests for the state holder, with the API mocked: each row of section 9.

## 9. Acceptance checklist

Set each mode in the dashboard (Settings → Sign In & Verification) and verify:

- [ ] `password`:
  - email + password signs in;
  - no code option is shown.
- [ ] `code`:
  - email → code → signed in;
  - a password login answers 403 and the app switches to the code flow.
- [ ] `password_or_code`: both paths work.
- [ ] `password_then_code`:
  - wrong password → 401 message;
  - right password → code step → signed in;
  - resend works with the ticket;
  - after 10 minutes the ticket expires → back to the password step.
- [ ] `per_user = true` (for example a "Student → code by SMS" exception):
  - a student's email → code step, with SMS shown;
  - an admin's email → password step;
  - an unknown email → the general mode, with no "not found" message.
- [ ] Personal QR → signed in immediately; a revoked code → 404 message.
- [ ] Company QR → company shown → email → code → signed in.
- [ ] Wrong code → 422 message, and the screen stays.
- [ ] Too many attempts → 429 countdown.
- [ ] Arabic (RTL) and English both render correctly.
- [ ] The university SSO path (`login_method = 2`) still behaves exactly as before.

## 10. Do NOT

- Don't change or remove the university (Keycloak) sign-in.
- Don't add endpoints, fields, or client-side mode logic beyond this brief.
- Don't cache `options()` / `identify()` results across app launches.
- Don't show raw exceptions or stack traces to the user.
