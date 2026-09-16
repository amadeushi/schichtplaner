---
name: Schichtplaner
description: Der Bon-Strang — shift tickets clipped over a dark espresso-umber counter (drawn from Zeus, the mascot's, fur), stamped in thermal-paper cream and printer black with one reserved red.
colors:
  paper-surround: "#332b1c"
  paper: "#faf3e3"
  ink: "#1c1917"
  ink-soft: "#6b6459"
  ink-line: "#ddd4bf"
  ink-line-strong: "#b9ad91"
  stamp: "#c8391f"
  stamp-wash: "#f7e3dc"
  danger: "#b3261e"
  danger-wash: "#f7e3dc"
  confirm-wash: "#eee8d8"
  surround-ink: "#f3e8d3"
  surround-ink-soft: "#a8967a"
typography:
  headline:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
    fontSize: "1.3rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "-0.01em"
  title:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
    fontSize: "0.95rem"
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: "normal"
  body:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
    fontSize: "0.9rem"
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: "normal"
  label:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "0.035em"
  mono:
    fontFamily: "ui-monospace, 'SF Mono', 'Roboto Mono', 'IBM Plex Mono', Menlo, Consolas, monospace"
    fontSize: "0.9rem"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "normal"
rounded:
  ticket: "2px"
  sm: "3px"
  pill: "999px"
  circle: "50%"
spacing:
  xs: "0.3rem"
  sm: "0.55rem"
  md: "0.75rem"
  lg: "1.1rem"
  xl: "1.5rem"
components:
  button-primary:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.paper}"
    rounded: "{rounded.sm}"
    padding: "0.55rem 1rem"
  button-primary-hover:
    backgroundColor: "#000000"
    textColor: "{colors.paper}"
  button-stamp:
    backgroundColor: "{colors.stamp}"
    textColor: "#fff8f4"
    rounded: "{rounded.sm}"
    padding: "0.55rem 1rem"
  button-stamp-hover:
    backgroundColor: "#a92c17"
    textColor: "#fff8f4"
  button-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "0.55rem 1rem"
  button-danger:
    backgroundColor: "transparent"
    textColor: "{colors.danger}"
    rounded: "{rounded.sm}"
    padding: "0.55rem 1rem"
  ticket:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.ticket}"
    padding: "1.1rem 1.1rem 1.25rem"
  ticket-hero:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.ticket}"
    padding: "1.4rem 1.4rem 1.6rem"
  badge-today:
    backgroundColor: "{colors.stamp-wash}"
    textColor: "{colors.stamp}"
    rounded: "{rounded.pill}"
    padding: "0.18rem 0.55rem"
  badge-neutral:
    backgroundColor: "{colors.confirm-wash}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    padding: "0.18rem 0.55rem"
  input:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "0.55rem 0.6rem"
---

# Design System: Schichtplaner

## Overview

**Creative North Star: "Der Bon-Strang" — the ticket rail over the pass**

Schichtplaner does not look like a scheduling SaaS. Its thesis, authored directly into the build (`public/partials/header.php`, first line of `<body>`), is that a shift plan is not a form — it is the string of order tickets clipped above a kitchen pass. Every shift is a perforated paper ticket with a scalloped punch-hole edge at top and a torn zigzag edge at bottom, printed in thermal-paper cream and printer black, and pinned to a dark espresso-umber counter — a color drawn directly from the coat of Zeus, the business's real mascot (a Great Dane), rather than an invented "app dark mode." One color is allowed to interrupt that monochrome: a stamped red, reserved for *today*, for *on-duty*, and for the two decisive "yes" actions (applying, approving). Everything else — confirmations, declines, pending states — stays achromatic, distinguished by weight, dash, and strike-through rather than hue.

The system is deliberately restrained because the product is: a self-hosted PHP/SQLite tool running on a resource-constrained Raspberry Pi, used one-handed by kitchen, counter, and delivery staff standing near the pass, not sitting at a desk. There is no JS framework, no animation library, no icon font — the ticket's texture comes from CSS gradients and pseudo-elements, and its rare iconographic marks are plain Unicode glyphs (✓ × › ‹), not an icon system. The admin sees the identical rail, with inline assignment controls folded into the same tickets rather than a separate management UI. The header carries the real AMDS wordmark (`public/assets/logo.png`), and the login screen introduces Zeus by photo (`public/assets/zeus.webp`) inside a stamp-red ring — the one deliberately warm, personal touch before the register-tape voice of the app itself takes over.

**Key Characteristics:**
- A perforated paper ticket (`.ticket`) is the one component every surface is built from, whether read-only (staff) or editable (admin).
- One warm stamp-red carries the entire system's visual emphasis; it is rationed, not decorative.
- Monospace is load-bearing: every time, date, and duration is set in it; names and prose stay in the sans.
- Thermal-paper-cream tickets and cards sit on a dark espresso-umber counter (`paper-surround`) — flat, hairline-bordered, no soft app-chrome, no drop-shadow elevation beyond a whisper of ambient lift under each ticket.
- Dashes mean "not yet settled" throughout the system: pending applications, unconfirmed rows, the off-duty ring, the rail's own connecting line.
- Real brand assets, not generic ones: the AMDS wordmark in the header and Zeus's photo on login are load-bearing identity, not decoration — this is why the surround color is sourced from Zeus's fur rather than a stock "warm dark" pick.

## Colors

The palette is a near-monochrome thermal-receipt paper stock (cream tickets and cards on a dark espresso-umber counter, printer-black ink) with exactly one chromatic accent rationed to meaning, not decoration. The counter color is not an invented "dark mode" — it was sampled from Zeus's coat (the business mascot) so the one non-paper surface in the system is still grounded in a real, owned asset rather than a generic dark palette.

### Primary
- **Stamp Red** (`#c8391f`): the one color allowed off the paper/ink axis. Used only for: the today/on-duty badge state, the on-duty duty-stamp control, the two affirmative "commit" actions (Bewerben/apply, Annehmen/approve), the ring around Zeus's photo on the login screen, and text-selection/caret color. It does not appear on any other button, badge, or decorative surface.
- **Stamp Wash** (`#f7e3dc`): the tinted paper behind stamp-red badges, on-duty rings, and error flashes — a faint red bloom rather than a solid fill.

### Neutral
- **Thermal Cream** (`#faf3e3`, token `paper`): the ticket, card, input, and header surface — the "paper" itself.
- **Espresso Counter** (`#332b1c`, token `paper-surround`): the page background the paper sits on — a dark umber sampled from Zeus's fur/mask, one shade dramatically darker than the ticket so tickets read as objects clipped above a real counter, not a card on a lighter tint of itself.
- **Counter Ink** (`#f3e8d3`, token `surround-ink`) and **Counter Ink Soft** (`#a8967a`, token `surround-ink-soft`): the text pair reserved for anything sitting directly on `paper-surround` rather than inside a `paper`-backed card or ticket — page `<h1>`s, the week navigator's label, and the rail's "Diese Woche" divider. `ink`/`ink-soft` remain correct *inside* any paper-colored surface; `surround-ink`/`surround-ink-soft` exist specifically so bare-on-counter text stays legible against the dark ground. When adding new bare-on-body text, reach for this pair rather than `ink`/`ink-soft`.
- **Printer Black** (`#1c1917`, token `ink`): primary text and borders *inside* paper-colored surfaces (cards, tickets, inputs) — not on the bare page background.
- **Soft Ink** (`#6b6459`, token `ink-soft`): secondary/meta text inside paper-colored surfaces — dates under headings, muted captions, uppercase eyebrow labels.
- **Hairline** (`#ddd4bf`, token `ink-line`): 1px dividers, table rules, dashed ticket-row separators.
- **Strong Hairline** (`#b9ad91`, token `ink-line-strong`): input borders, secondary-button borders, dashed states (off-duty ring, pending shift-chip border), the rail's dashed connector, the scrollbar thumb.
- **Confirm Wash** (`#eee8d8`, token `confirm-wash`): the background for settled/confirmed states (confirmed badges, filled-shift badges) *and*, doing double duty, the weekend-column tint in the admin week grid. One token, two roles — both read as "this cell is not a plain workday."

### Named Rules
**The One Stamp Rule.** Stamp red appears in exactly one register: *today, on duty, or "yes."* A destructive action (delete, remove assignment, logout, decline) never borrows it — that is `danger` (`#b3261e`), a separate, visually adjacent but distinct token. If a new screen needs emphasis, reach for weight, a dashed border, or uppercase tracking before reaching for a second red.

**The Achromatic Badge Rule.** Status badges (confirmed, pending, declined, filled, open) stay on the ink/paper/confirm-wash axis. Only `.badge.today` and `.badge.active-duty` earn the stamp. A badge's color is not how you tell states apart — its border style (solid vs. dashed vs. strike-through) is.

**The Bare-Ground Rule.** Any element that sits directly on `paper-surround` — not nested inside a `.card`/`.ticket` — must use `surround-ink`/`surround-ink-soft`, never `ink`/`ink-soft`, which are calibrated for the light paper surfaces and go low-contrast on the dark counter. `login.php`'s `<h1>` is the one documented exception (it sits inside the login `.card`, so it correctly stays `ink` via a more specific `.card h1` override).

## Typography

**Body/Sans Font:** -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif
**Mono Font:** ui-monospace, "SF Mono", "Roboto Mono", "IBM Plex Mono", Menlo, Consolas, monospace

**Character:** A quiet system sans for names, titles, and prose, paired with a tabular monospace reserved for anything that is a measurement of time — the register-tape voice next to the human one. The pairing is the direction contract's own thesis line: "Monospace für Zeiten, ruhiger Sans für Namen."

### Hierarchy
- **Headline** (700, 1.3rem, 1.25 line-height, -0.01em): the single `<h1>` per screen ("Mein Plan," "Schichtplan," "Zeiterfassung").
- **Title** (700, 0.95rem–1.25rem for the hero ticket): the ticket's day name (`ticket-day`) and card sub-headers; the hero ticket scales this up to 1.25rem to mark today as physically larger.
- **Body** (400, ~0.85–0.9rem, 1.4 line-height): ticket-meta captions, table cells, muted paragraph text.
- **Label** (700, 0.72–0.78rem, uppercase, 0.03–0.04em tracking): section headings (`h2`), form field labels, badges, the rail's "Diese Woche" divider — the system's eyebrow voice, always uppercase, always `ink-soft` unless the badge is stamp-red.
- **Mono** (600, ~0.8–0.92rem, tabular-nums): shift times (`ticket-time`), dates (`ticket-date`), durations, the brand wordmark, and every bottom-tab label — even navigation labels are set in mono, tying "this is data / this is structure" to the typeface itself.

### Named Rules
**The Register-Tape Rule.** Any value a user could look up on a time clock — a clock-in, a shift start/end, a date, a duration, an hours total — is set in mono with tabular-nums. Nothing else is. This is the fastest way to tell "this is a fact" from "this is a name" at a glance, one-handed, in a kitchen.

## Layout

Mobile-first, single-column, content capped at `max-width: 640px` and centered — the same constraint on `<main>` and on the bottom tab bar's inner row, so the tab bar visually caps the same column the content sits in. `.grid-2` (used in forms) is a two-column grid that collapses to one column under 640px; the admin week table becomes a horizontally-scrolling `.grid-scroll` region with a sticky first (day) column rather than reflowing, since a 7-day grid has no honest single-column form.

The **rail** (`.rail`) is the system's core spatial device: a vertical flex column of tickets, `gap: 1rem`, with a dashed vertical line (`.rail::before`, a repeating 2px-wide linear-gradient) running behind them left-of-center — the physical string the paper tickets are threaded on. Today's ticket, when present, sits *outside* and above the rail as an enlarged hero, with a `.rail-label` ("Diese Woche") marking where the strung week begins beneath it.

Structural chrome stays thin and sticky rather than heavy: a slim `topbar` (0.7rem/1.1rem padding) pinned to the top holds only the wordmark and the current user's name; the fixed bottom `tabbar` (62px + safe-area-inset-bottom) is the primary navigation for both roles and is the one persistent UI element on every logged-in screen.

## Elevation & Depth

The system is almost flat. Cards carry only a 1px hairline shadow (`0 1px 0 rgba(28,25,23,0.04)`) — barely more than a border. Tickets get slightly more: the same hairline plus a soft, tight ambient shadow (`0 8px 16px -12px rgba(28,25,23,0.4)`) so each one reads as a separate slip of paper lifted just off the rail behind it, not as a bordered box. There is no hover-elevation change anywhere, and no shadow scale beyond these two values — depth in this system is conveyed by paper-on-paper layering (`paper` ticket on `paper-surround` background) and by the ticket's own cut edges, not by a shadow vocabulary.

### Shadow Vocabulary
- **Card lift** (`box-shadow: 0 1px 0 rgba(28,25,23,0.04)`): flat surfaces — `.card`.
- **Ticket lift** (`box-shadow: 0 1px 0 rgba(28,25,23,0.05), 0 8px 16px -12px rgba(28,25,23,0.4)`): every `.ticket`, the rail's paper-off-the-string read.

### Named Rules
**The No-Elevation-On-Interaction Rule.** Nothing gains a shadow on hover or focus. State change is communicated by border color (`--stamp` for today/on-duty), by the duty-stamp's rotation and scale, or by badge/text changes — never by lifting a surface further.

## Shapes

Corners stay close to the right angle: `3px` on cards, buttons, and inputs; `2px` on tickets, the tightest radius in the system, reinforcing that a ticket is cut paper, not a rounded app-chrome card. The one full-circle exceptions are deliberate and load-bearing: the `duty-stamp` control (10rem diameter, the Stempeln screen's entire primary interaction) and the small `stamp-mark` indicator (2.6rem, "IM DIENST" on today's hero card) — both round because they are literally a stamp, not a button. Pills (`border-radius: 999px`) are reserved for badges only.

The ticket's signature silhouette is built from two CSS pseudo-elements rather than an image: `::before` paints a repeating radial-gradient row of cream-colored punch holes along the top edge (scalloped, 24px × 16px tile), and `::after` paints a repeating pair of 45°-opposed linear gradients along the bottom edge to fake a torn zigzag tear. Dashed 1px borders (`border-top: 1px dashed`) separate rows within a ticket and mark anything provisional: pending badges, the off-duty ring, unclaimed shift chips, and the rail's own connecting line.

## Components

### Buttons
- **Shape:** 3px radius, 1.5px solid border, small horizontal padding (`0.55rem 1.1rem`; `.small` variant `0.4rem 0.75rem`).
- **Touch target:** every `.btn` is a flex box centering its own label/glyph, `min-height: 2.75rem` (44px) for full-size buttons and `min-height`/`min-width: 2.25rem` (36px) for `.small` — the floor for a one-handed, kitchen-counter tap target, including icon-only buttons (the ticket rail's "×" remove control, the week-navigator's "‹"/"›" arrows), which get a genuinely square hit area rather than shrinking to their glyph's own size.
- **Primary** (`.btn`): solid ink-black fill, cream text — the default, low-emphasis-by-contrast action.
- **Stamp** (`.btn.stamp-btn`): solid stamp-red fill — reserved for "Bewerben" (apply) and "Annehmen" (approve), the system's two yes-actions.
- **Secondary** (`.btn.secondary`): `paper` fill, ink text, `ink-line-strong` border; hovers to `confirm-wash`. Filled rather than transparent so it stays legible wherever it sits directly on the dark `paper-surround` (e.g. the week-navigator arrows, today's "Stempeln" shortcut).
- **Danger** (`.btn.danger`): transparent fill, `danger`-red text and border; used only for delete/remove/logout — never shares a screen role with the stamp button.
- **Active/disabled:** `:active` nudges the button down 1px (`translateY(1px)`); `:disabled` drops to 0.45 opacity, cursor not-allowed.

### Named Rules
**The Comfortable-Tap Rule.** No interactive control — button, select, or input — renders below a 36px edge, and primary-flow buttons (`.btn` without `.small`) hold to 44px. `.small` exists to keep dense rows (a ticket's assign/decide controls, a table's row actions) visually compact; it is a padding and type-scale reduction, never a touch-target reduction. Any ad hoc inline sizing on a `<select>` or `<button>` that drops below this floor is drift, not a legitimate exception — use the `.select-inline` utility class for a compact, auto-width select instead of a one-off inline style.

### Cards / Containers
- **Corner style:** 3px.
- **Background:** `paper` on `paper-surround`.
- **Border:** 1px `ink-line`.
- **Shadow:** the hairline-only "Card lift" (see Elevation).
- **Internal padding:** `1.1rem`.

### Tickets (signature component)
The system's one true custom component; every shift, on every surface, staff or admin, is a `.ticket`. Perforated top and torn bottom (see Shapes), 1.5px `ink-line` border that turns stamp-red on `.today`/`.on-duty`, dashed internal row dividers, a head row pairing the sans-set day name against the mono-set date. The **hero variant** (`.ticket.hero`, today's card on the staff "Mein Plan" screen) is not a modifier class alone — it is enlarged (1.4rem/1.6rem padding vs. 1.1rem/1.25rem) and its day/date type step up a size, so today is unmistakably the largest object on the screen, not just an outlined ticket in its normal weekday slot.

### Duty Stamp (signature component)
The Stempeln (clock-in/out) screen's entire primary control: a 10rem circle. **Off-duty** is a dashed `ink-line-strong` ring with muted text ("Nicht im Dienst"). **On-duty** is a solid 4px stamp-red ring, rotated -6°, with `mix-blend-mode: multiply` so it reads as pressed ink rather than a flat red circle, showing the clock-in time in mono beneath the label. The smaller `stamp-mark` badge (today's hero card, "IM DIENST") is the same rotated-stamp device at 2.6rem.

### Badges
- **Style:** pill (999px), 1.5px `border: currentColor`, uppercase 0.72rem label type.
- **Achromatic states:** confirmed/approved/open → ink on confirm-wash; pending → ink-soft, dashed border, transparent; declined/rejected/withdrawn/closed → ink-soft, strike-through, 0.75 opacity.
- **Stamp state:** today/active-duty → stamp-red text and border on stamp-wash. The only chromatic badge.

### Inputs / Fields
- **Style:** 1.5px `ink-line-strong` border, 3px radius, cream background, full width, `min-height: 2.75rem` (same 44px floor as a primary button; checkboxes/radios are exempt and stay their native size).
- **Focus:** 2px solid ink outline, 1px offset — no glow, no color shift, no border-color change.
- **Labels:** uppercase, 0.78rem, `ink-soft`, sit above the field (not floating/inline).
- **Compact inline select:** `select.select-inline` (auto-width, `min-height: 2.25rem`) for a dropdown that must sit inline in a row rather than fill it (e.g. a shift's status selector next to its delete control) — the one sanctioned way to shrink a field below the default full-width/44px treatment; a bare inline `style` doing the same is drift.

### Navigation
- **Top bar:** slim, sticky, the real AMDS wordmark image (`public/assets/logo.png`, 20px tall) on `paper`; current user's name as a quiet link at the right. Not a nav surface — brand + context only.
- **Bottom tab bar:** fixed, 62px + safe-area, the actual navigation for both roles. Mono, uppercase, 0.72rem labels; inactive tabs are `ink-soft`, the active tab turns stamp-red text with a small solid dot (`::before`) above it — the tab bar is the one place outside the ticket/badge system that reuses the stamp accent, and it does so for exactly one state (active), consistent with the One Stamp Rule.
- **Admin decision surface:** every assign/approve/reject control lives inside the ticket rail on `admin/shifts.php` and nowhere else. `admin/applications.php` is a deliberately read-only, week-spanning history view — its own code comment states this is intentional, so there is exactly one place an admin decision is made.

## Do's and Don'ts

### Do:
- **Do** reserve stamp red (`#c8391f`) for today/on-duty state and the two commit actions (apply, approve) — everywhere else stays on the ink/cream/confirm-wash axis.
- **Do** give every shift ticket the perforated top and torn-zigzag bottom edge; it is the one non-negotiable signature of the "Bon-Strang" world, not an effect reserved for the hero card.
- **Do** set every time, date, and duration in mono with tabular-nums; keep names and prose in the sans.
- **Do** use a dashed border to mean "not yet settled" (pending applications, off-duty ring, unfilled shift chips, the rail's connecting line) and a solid border to mean settled.
- **Do** keep the admin's shift-decision controls (assign/approve/reject) inside the ticket rail on `admin/shifts.php` only; any other admin surface showing applications must stay read-only history.
- **Do** reach for plain Unicode glyphs (✓ × › ‹) for the rare iconographic need — never an icon font or SVG icon library, consistent with the Pi/mobile-data resource budget.
- **Do** use `surround-ink`/`surround-ink-soft` for any text placed directly on the page background; reach for `ink`/`ink-soft` only inside a `paper`-colored card or ticket (see the Bare-Ground Rule under Colors).

### Don't:
- **Don't** introduce a second chromatic accent. `danger` (`#b3261e`) already covers destructive actions and must not be blended into "emphasis" or "today" contexts, even though its hex sits close to `stamp`.
- **Don't** round any rectangular surface past 3px (buttons/cards/inputs) or 2px (tickets) — the geometry stays close to cut paper, not soft app-chrome. Circles (duty-stamp, stamp-mark, badge pills) are the sanctioned exception, not a precedent for other rounded shapes.
- **Don't** add elevation on hover/focus. State changes through color, rotation, or scale — never through a bigger shadow.
- **Don't** ship a heavy animation library, icon font, or client-side JS framework/bundle; the product's hard technical constraint is a memory-limited Raspberry Pi serving other services, and the entire visual system (ticket perforation, stamp rotation, dashed rails) is built from plain CSS for exactly this reason.
- **Don't** add a second place to approve or reject a shift application. One decision surface, the ticket rail, is a confirmed system rule, not a page-specific convenience.
- **Don't** revert `paper-surround` to a generic warm cream/off-white page background. The dark espresso-umber counter is deliberately sourced from the real mascot photo (Zeus) to avoid the generic "warm cream ground" AI-interface tell — the paper tickets and cards stay cream, but the page they sit on does not.
- **Don't** place `ink`/`ink-soft` text directly on `paper-surround` — it was legible on the old light surround but is low-contrast on the current dark one. Use `surround-ink`/`surround-ink-soft` instead (the Bare-Ground Rule).
