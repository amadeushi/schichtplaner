# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Two roles, both overwhelmingly on smartphones:

- **Mitarbeiter (staff):** kitchen, service, counter ("Tresen"), and delivery/rider staff at a gastronomy/delivery business. Check the schedule and clock in/out between tasks — on the move, often with one hand free, in a kitchen or on a delivery run. Sessions are short and frequent, not deskbound.
- **Admin (owner/manager):** plans and assigns shifts, approves applications, reviews worked hours for payroll. Also primarily mobile — manages the roster from wherever they are, not chained to an office desktop. Desktop use happens but is secondary for both roles.

## Product Purpose

A self-hosted shift-scheduling and time-tracking tool for a small gastronomy/delivery team. Staff apply for open shifts or get assigned directly by the admin; both sides get notified (email/webhook) on changes. A per-employee-unlockable clock-in/clock-out feature produces the hours records the admin needs for monthly payroll.

## Positioning

Where SaaS scheduling tools (e.g. edtime.de) require a subscription and hand the business's staff/payroll data to a third party, this runs entirely on hardware the business already owns (a Raspberry Pi in the back office), for the data of a small team. No per-seat fees, no vendor lock-in, full control over where employee and payroll-relevant data lives.

## Operating Context

- Runs on a Raspberry Pi (currently headless, no GUI) on the business's own network, reachable both on the local Wi-Fi and — since a recent step — over the public internet via a real domain and TLS, so staff can reach it from home or on mobile data, not just on-site.
- The primary real-world moment is standing near the pass or the counter, phone in hand, deciding "am I working today" or tapping in/out — not sitting down to plan.
- German-language interface; business is Austrian (amds.at). All UI copy uses proper German orthography (ä/ö/ü/ß) rather than ASCII transliteration (ae/oe/ue) — the stack fully supports this end to end (UTF-8 templates, `htmlspecialchars(..., 'UTF-8')` output escaping, SQLite's native UTF-8 text storage), so there is no technical reason to fall back to transliteration anywhere in the app.
- Admin-configured notifications (email via SMTP, optional n8n webhook) tell staff when a shift decision lands, without them needing to keep the app open.

## Capabilities and Constraints

- **Confirmed today:** browse/apply for open shifts, admin approve/reject/direct-assign, a Mo–Su admin scheduling grid, a desktop drag-and-drop calendar view (with a pending-applications badge per shift that opens an inline dialog to approve/reject right there, sharing the same decision logic as the main schedule view; every pending application also appears as its own draggable tile in the applicant's row, which can be dragged onto a different shift to re-target it — e.g. correcting a wrong same-day start time — with the exact target shift live-highlighted while dragging), a draft/publish workflow (new shifts and assignment changes stay invisible to staff and unnotified until the admin explicitly publishes a week, batching everything into one generic "your schedule changed" email per affected person), a per-employee "Meine Woche" view, opt-in-per-employee clock-in/out with a monthly hours view for the admin (entries editable/deletable by the admin), email + webhook notifications, account lockout after repeated failed logins, admin-editable subject/text for every system email (with placeholder substitution, falling back to a built-in default), a configurable cap on the notification log with a manual cleanup action, and a CSV export of working hours (single employee or all at once) for payroll/documentation purposes.
- **Hard technical constraint:** plain PHP + SQLite, no framework, no JS build step, no heavy client-side framework — the whole app must keep running comfortably on a shared, memory-constrained Pi (well under 1GB RAM total, shared with other services on the box). Any new GUI direction must stay light: no large JS bundles, no heavy animation libraries, no asset-heavy pages that would strain either the Pi or a phone on mobile data.
- **Undecided:** shift "codes"/short labels and employee skill tags (seen in a competitor reference screenshot) were explicitly deferred, not built — today's data model is title/date/time/location/needed-count, nothing more granular.

## Brand Commitments

The business is "Amadeus Delivery" (AMDS). A real wordmark logo and a mascot photo (Zeus, a Great Dane) are confirmed brand assets and are load-bearing in the current design — the page's dark counter background is itself sampled from Zeus's coat rather than an invented color. The app's internal name/setting still reads "Schichtplaner"; renaming it to AMDS-branded copy has not been requested and is left to the admin's own Einstellungen page if wanted.

## Evidence on Hand

- A screenshot of a competitor tool (edtime.de-style) was shared earlier as a structural reference for a Mo–Su grid, KW/week navigation, and colored day columns — informative for layout patterns, not a binding visual direction.
- The AMDS wordmark (`amds.png`, used in the app as `public/assets/logo.png`) and a mascot photo of Zeus, the business's dog (`zeus_amadeusdelivery.png`, used as `public/assets/zeus.webp`/`.png`), were provided directly by the user and are now integrated: the logo in the header on every screen, Zeus's photo on the login screen, a small Zeus emblem next to the header wordmark, and a cropped Zeus head as the browser favicon/apple-touch-icon (all cropped from the same source photo).

## Product Principles

1. **Mobile is the primary surface for everyone**, admin included — design and validate for the phone first, let desktop inherit.
2. **The three core jobs must be reachable in as few taps as possible**: see today/this week's plan, apply or get assigned, clock in/out. Nothing should bury these behind navigation.
3. **Stay light.** Every visual decision is weighed against the Pi's and the phone's resource budget — no gratuitous JS, no heavy asset payloads.
4. **Legible under real conditions**: used one-handed, often in a kitchen or on the street, sometimes in bright daylight or dim back-of-house lighting — clarity beats decoration.
5. **Self-hosted, not SaaS-generic** — the product doesn't need to look like a template scheduling app; it can have a point of view.
