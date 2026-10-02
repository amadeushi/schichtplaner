---
target: schichtplaner
total_score: 33
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 1
target_identity: "file:/Users/hamunhirbod/claude_code/schichtplaner/schichtplaner"
timestamp: 2026-10-02T01-45-25Z
slug: schichtplaner
---
Method: dual-agent (A: a230860cf7d0b151c · B: adf684b081bda9af8)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3/4 | "Im Dienst seit HH:MM" / "Aktuell: X" on time_tracking.php render once at page load and never tick — a mid-shift glance shows stale data. |
| 2 | Match System / Real World | 4/4 | Solid German domain copy throughout ("Bewerben", "Annehmen", "Personal gesucht"). |
| 3 | User Control and Freedom | 3/4 | No undo after a shift delete or a sent publish — `confirm()` is the only safety net on one-way doors. |
| 4 | Consistency and Standards | 3/4 | Detector found 21 advisory token-drift findings against DESIGN.md's own documented scale: 19 off-ramp font-sizes, 1 off-scale border-radius, 1 undocumented color (see Deterministic scan below). |
| 5 | Error Prevention | 2/4 | `admin/shifts.php`'s `publish` action (mass email/SMS, irreversible) has no `confirm()` guard, unlike every other consequential action in the same file (delete, unassign, clear_sms_key). |
| 6 | Recognition Rather Than Recall | 4/4 | Edit forms pre-fill current values; `.settings-jump` nav; hidden `return_date`/`week_date` fields preserve context. |
| 7 | Flexibility and Efficiency | 3/4 | CSV export, `.ics` subscription, `repeat_weeks`, auto-submit selects are real efficiency wins; no bulk actions, no keyboard shortcuts. |
| 8 | Aesthetic and Minimalist Design | 4/4 | The ticket/rail composition is genuinely restrained; no gratuitous chrome. |
| 9 | Error Recovery | 4/4 | Error copy is specific and actionable (e.g. SMS key validation, "Diese Schicht ist bereits voll besetzt"). |
| 10 | Help and Documentation | 3/4 | No searchable help, but extensive inline `muted` explanatory copy substitutes reasonably well for a small self-hosted tool. |
| **Total** | | **33/40** | **Good** |

## Design Specificity Verdict

**LLM assessment**: This is not a reskinned generic scheduler. The "Bon-Strang" ticket metaphor (perforated top, torn-zigzag bottom, built from pure `::before`/`::after` CSS gradients, not images), the espresso-umber counter colour sampled from the real mascot Zeus's coat, the stamp-red reserved for exactly four registers (today / on-duty / yes-actions / urgent), and the register-tape mono-for-time rule are load-bearing, specific decisions that show up consistently in code, not just in the brief. The one place specificity thins is the admin CRUD chrome (settings forms, user table) — competent but closer to generic admin-panel convention than the ticket world; DESIGN.md itself names this the deliberate "quiet register-tape voice," so it reads as intentional restraint rather than a miss.

**Deterministic scan**: `impeccable detect --json public/` directory-mode returns exit 0 / `[]` — but this is a tooling blind spot, not a clean bill of health: the detector's shipped binary has no `.php` extension handling in directory-walk mode and silently skips all 26 PHP files. Scanning each file directly (bypassing the walker) surfaces real findings, all `severity: advisory`:
- **design-system-font-size (19)**: one-off sizes outside DESIGN.md's 5-step ramp (headline/title/body/label/mono) — `public/partials/header.php` (15 instances, lines 83–460), `public/admin/settings.php:201`, `public/partials/admin_day_ticket.php:39,70,84`.
- **design-system-radius (1)**: `header.php:52`, `border-radius: 5px` vs. the documented 2px/3px/999px/50% scale.
- **design-system-color (1)**: `header.php:250`, `.flash.error { color: #7a2213; }` — a third red not in the 13-color documented palette (`--danger:#b3261e`, `--stamp:#c8391f` are the documented neighbors).

These are genuine drift against a project that documents its tokens unusually strictly, not rule noise — none of the project's *intentional* design language (stamp accent, mono time styling, perforated-ticket motif) was flagged. One isolated-fragment false positive was identified and ruled out: scanning `header.php` alone (stripped of the `.card`/`.ticket` wrappers that always surround `--ink` text in the real pages) produced a spurious low-contrast finding that doesn't reflect any real rendered page, per the codebase's own documented "Bare-Ground Rule."

**Live evidence**: The production login screen was opened directly (desktop + mobile 375px) and its rendered DOM matches `header.php`/`login.php` line-for-line, including the just-shipped `--surround-ink-soft: #b4a58c` contrast fix — confirming v1.21.0 is genuinely deployed, not just claimed. No console errors, no failed network requests, no layout/overflow issues at either width. No credentials were available, so authenticated screens (the actual shift grid, settings, admin views) were assessed from source only, not rendered — flag this if you want a fully rendered pass later.

## Overall Impression

The six v1.21.0 fixes landed cleanly and the score moved in the right direction (see trend below) — this is now a "Good" interface with real, specific craft. The biggest remaining gap isn't visual, it's a safety-net inconsistency: the app is disciplined about confirming destructive actions almost everywhere except its single highest-consequence one (publishing a week, which fires real emails/SMS to real people and can't be undone). The second-biggest opportunity is cheap: 21 small, specific token-drift instances the project's own documented system would reject, plus a fast fix for a dead-end at the exact worst moment (locked out at login, about to start a shift).

## What's Working

- **`.shift-time`/`shiftTimeHtml()`** (`header.php:297–307`) — the big-bold-start/small-soft-end typographic split is a real, documented fix to a real misreading problem, not decoration.
- **Ticket perforation/tear** (`.ticket::before`/`::after`, `header.php:277–288`) — a genuine signature device built from pure CSS gradients, honoring the no-icon-library/no-images constraint while staying distinctive.
- **The 48h-gated "Personal gesucht" button** (`admin_day_ticket.php:52–56,133`, v1.21.0) — a time-conditional application of the One Stamp Rule rather than a static color choice; most "add urgency" features skip this kind of restraint.
- **Deploy integrity confirmed independently**: the live site's rendered DOM matches source exactly, including the contrast fix's inline rationale comment — the deploy pipeline is trustworthy, not just fast.

## Priority Issues

**[P1] "Woche veröffentlichen" has no confirmation despite being the highest-consequence action in the app**
- **Why it matters**: `admin/shifts.php`'s `.publish-bar` form (lines 454–465) submits directly on click; the handler (lines 263–347) immediately emails/SMS-notifies every affected employee and can't be undone. Every other consequential action in the same file (delete, unassign, clear_sms_key) has a `confirm()` guard — publish, with strictly larger blast radius, has none.
- **Fix**: add a `confirm()` stating the exact numbers already computed (`$draftCount`/`$pendingNotifyCount`): "3 Entwürfe und 5 Benachrichtigungen jetzt an betroffene Mitarbeiter senden?"
- **Suggested command**: `/impeccable harden`

**[P2] 21 design-token drift instances against DESIGN.md's own documented scale**
- **Why it matters**: 19 one-off font-sizes, 1 off-scale border-radius, and 1 undocumented color have accumulated in `header.php`, `admin/settings.php`, and `admin_day_ticket.php` against a system that documents its tokens unusually strictly — exactly the kind of drift that compounds silently in a single-CSS-file, no-build-step project with no linting backstop.
- **Fix**: snap each flagged value to the nearest documented token (headline/title/body/label/mono sizes; the 2px/3px/999px/50% radius scale; replace `#7a2213` with `--danger` or add it to DESIGN.md as an intentional new token if it's meant to differ).
- **Suggested command**: `/impeccable polish`

**[P2] No recovery path for a locked-out or forgetful employee**
- **Why it matters**: `login.php` has no "Passwort vergessen" link; the lockout message offers only a wait timer. The admin-reset tool already exists (`admin/users.php` → "Passwort zurücksetzen") but is never surfaced to the person actually stuck, at exactly the moment — standing at the pass, about to clock in — PRODUCT.md names as the primary real-world moment.
- **Fix**: one line of copy under the login error/lockout state pointing to contacting the admin; no new flow needed since the backend capability already exists.
- **Suggested command**: `/impeccable clarify`

**[P2] "Neue Schicht anlegen" is a permanently-expanded 8-field form on a page visited for glancing**
- **Why it matters**: `admin/shifts.php` (lines 526–568) renders the full creation form unconditionally at the bottom of every week view, violating the progressive-disclosure pattern the codebase already establishes elsewhere (`.settings-jump`, `<details class="tpl-editor">` in `admin/settings.php`) — clutter on the one screen an admin opens multiple times a day mostly to check staffing.
- **Fix**: wrap it in the same native `<details>` pattern already used for email templates, collapsed by default.
- **Suggested command**: `/impeccable distill`

**[P3] Duty-stamp/elapsed-time displays are static, not live**
- **Why it matters**: `time_tracking.php`'s "Im Dienst seit HH:MM" and "Aktuell: X" (lines 128–133, 144) are computed once at page load and never update without a manual refresh — undercutting the Register-Tape Rule's "this is a fact you can trust" premise.
- **Fix**: a ~10-line vanilla `setInterval` ticking the mono span; well within the no-framework/no-heavy-JS constraint.
- **Suggested command**: `/impeccable polish`

**[P3] Auto-submitting selects are a silent risk for keyboard/mobile users**
- **Why it matters**: the assign dropdown (`admin_day_ticket.php:117`) and status select (line 147) submit instantly on value change — a keyboard user arrowing through options, or a thumb mis-tapping a native mobile `<select>` while scrolling, can trigger a real assignment/status change (plus a queued notification) with no chance to reconsider. A WCAG 3.2.2 "On Input" concern as much as a mobile one.
- **Fix**: either require an explicit "Zuweisen" submit button, or add a one-time undo toast offering to revert the just-made change.
- **Suggested command**: `/impeccable harden`

## Persona Red Flags

**Casey (mobile)**: The assign dropdown's instant-submit-on-change is a real fat-finger risk on a touchscreen select mid-scroll — no confirmation, immediate side effect. No disabled/loading state on any submit button (`publish`, `assign`, `urgent_reminder`) — on the flaky mobile data explicitly named in PRODUCT.md, a slow response invites a second tap and a duplicate action.

**Riley (stress-tester)**: The `publish` handler sends notifications, then deletes the matching `pending_notifications` rows at the very end of the same request (`admin/shifts.php:294–337`) — a second rapid click before the first completes could re-read the same not-yet-deleted rows and double-send real emails/SMS to the same people. No rate-limit or "already reminded recently" guard around `urgent_reminder`, unlike the documented 15-minute SMS coalescing window used elsewhere.

**Sam (accessibility)**: Glyph-only delete button `&times;` (`admin_day_ticket.php:158`) has no `aria-label` — a screen reader announces "×," not "Schicht löschen." Native HTML5 drag-and-drop in `admin/calendar.php` has no documented keyboard-equivalent for re-targeting applications, silently pushing keyboard-only admins back to the simpler `admin/shifts.php` controls with no explanation. The new `--surround-ink-soft` (#b4a58c) passes AA at ~6.8:1 against the darker gradient stop but only ~4.74:1 against the lighter `--paper-surround-top` stop — a genuine pass, but with little margin for any future gradient tweak.

## Minor Observations

- Checkboxes across `settings.php`/`profile.php` carry a redundant inline `style="width:auto;"` duplicating the global rule already in `header.php:191`.
- The footer's version number links out to a public GitHub changelog — the only external hyperlink inside otherwise fully internal, phone-first logged-in chrome.
- Browser-native `confirm()` dialogs break the paper/ticket illusion for a moment — an accepted platform seam, not a defect.
- `admin_day_ticket.php` leans on inline `style="..."` for layout rather than utility classes — not visible, but maintenance debt if the component grows.
- Tooling note: `impeccable detect`'s directory-walk mode has no `.php` extension handling and silently returns `[]` for this entire project; per-file direct scans are needed to get real signal. Worth knowing for any future critique/audit run on this codebase.

## Questions to Consider

1. What if "Woche veröffentlichen" had to show the literal list of names it's about to notify before the admin could confirm — would that make the action feel as deliberate as the One Stamp Rule insists everything stamp-red should be?
2. What if a locked-out employee saw Zeus again at that exact failure moment — a small, warm "frag deinen Admin" note next to his photo — turning the one cold dead-end into a second deliberate appearance of the brand's one personal touch?
3. Is the "no live clock tick" constraint really in tension with the Pi's resource budget, or is that being applied more conservatively than it needs to be for a ~10-line `setInterval`?
