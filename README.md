# Wise Mirror Booking

A custom WordPress plugin for **TheWiseMirror.com**: a multi-step booking form with drag & drop photo uploads, Stripe Checkout with verified-only confirmation, a fully editable session/pricing system, and a redesigned dashboard — plus an internal REST API and AI provider integration, both built for future expansion.

## Install

1. Upload the `wise-mirror-booking` folder to `wp-content/plugins/`, or upload the ZIP via **Plugins → Add New → Upload Plugin**.
2. **If replacing an earlier version:** deactivate and delete the old copy first, then upload fresh — WordPress's uploader won't overwrite an existing plugin folder, so skipping this step is the most common reason updates don't appear to take effect.
3. Activate **Wise Mirror Booking**.
4. Go to **Wise Mirror** in the admin sidebar — Dashboard → Overview has a Quick Start checklist.
5. Set up your sessions under **Bookings → Session Management**, and your working hours under **Bookings → Availability**.
6. Place `[wise_booking_form]` on your booking page, and add your Stripe keys under **System Settings → Payments**.
7. Run one full test booking in Test mode before switching to Live.

## Dashboard structure (v1.2)

The old flat list of 15 tabs is now 8 top-level items, grouped:

```
Dashboard        → Overview, Analytics
Form Editor      → HTML / CSS / JavaScript / Live Preview, all on one screen
Bookings         → Booking Mapping, Session Management, Pricing, Availability, Submissions, Payments
AI Configuration → External AI provider setup (OpenAI / Claude / Gemini / Custom)
API Manager      → Internal REST API credentials, usage, docs, webhooks
System Settings  → General, Payments, Email, Notifications, Uploads, Debug, Cache, Logs, Security, Performance, License, Version
Tools            → Export / Import / Reset
Help & Documentation
```

Old bookmarked URLs (e.g. `tab=pricing-mapping`, `tab=submissions`) still work — they redirect to the new nested location.

## Folder structure

```
wise-mirror-booking/
├── wise-mirror-booking.php            Bootstrap, activation/upgrade hooks, autoloader
├── uninstall.php                      Cleans up options on plugin deletion (booking/payment data kept by default)
├── includes/
│   ├── class-wise-mirror-plugin.php         Core singleton
│   ├── class-wise-mirror-activator.php      Table creation, seeding, auto-upgrade of stale form assets
│   ├── class-wise-mirror-db.php             CRUD + analytics queries for submissions/payments
│   ├── class-wise-mirror-sessions.php       Fully editable sessions (replaces the old fixed 4-package list)
│   ├── class-wise-mirror-settings.php       Option getters/sanitizers, default form HTML/CSS/JS
│   ├── class-wise-mirror-schedule.php       Available time-slot calculation
│   ├── class-wise-mirror-cache.php          Transient-based caching helper
│   ├── class-wise-mirror-stripe-client.php  Minimal Stripe REST client
│   ├── class-wise-mirror-ai-client.php      Routes to the configured AI provider (real HTTP calls)
│   ├── class-wise-mirror-api-manager.php    Internal API key/secret/token + usage stats
│   ├── class-wise-mirror-api-auth.php       Authenticates internal API requests
│   ├── class-wise-mirror-api-registry.php   Internal API route definitions — doubles as the docs source
│   ├── class-wise-mirror-webhooks.php       Outgoing webhook dispatch on real events
│   ├── class-wise-mirror-shortcode.php      Renders [wise_booking_form]
│   ├── class-wise-mirror-ajax.php           Photo pre-upload + final booking submission
│   ├── class-wise-mirror-rest-api.php       Public routes: verify-payment, stripe-webhook, available-slots
│   ├── class-wise-mirror-email.php          Confirmation email rendering + SMTP/wp_mail
│   ├── class-wise-mirror-admin.php          Grouped sidebar nav, routing, all settings save handlers
│   └── class-wise-mirror-logger.php         Categorized log (info/error/debug/ai/api) with search/filter/export
├── admin/views/                        One file per dashboard page (see structure above)
└── assets/                             admin.css/js (dashboard), frontend.css (base reset only —
                                         the form's real CSS/JS is edited from Form Editor)
```

## The booking form (multi-step)

1. **Details** — package selection (card grid), name/email/phone/country/preferred contact, birth date, and your preferred date & time (checked live against Availability).
2. **Photos** — drag & drop or click to upload, multiple images per category, live thumbnail preview, real upload progress, remove individual images. Each photo uploads immediately (`wise_upload_photo`); the final submission just sends the resulting URLs.
3. **Your Message** — question/message, optional additional notes, then Continue to Payment.

## How payment verification works

No booking is ever confirmed off something the browser reports. After Stripe redirects the customer back, the plugin calls Stripe's API directly to check `payment_status`, and Stripe's signed webhook provides a second, independent confirmation. Only then does the booking get marked confirmed and the email (and any subscribed webhook) fire.

## AI Configuration & API Manager — what's real vs. "Coming Soon"

**Real and working today:**
- AI provider connections (OpenAI, Claude, Gemini, or a custom endpoint) — the "Test Connection" button makes an actual API call.
- Internal REST API at `/wp-json/wise/v1/api/` — auto-generated Key + Secret on activation, with live endpoints for `bookings`, `bookings/{id}`, `customers`, `sessions`, `payments`, and `ai/generate` (routes a prompt through your configured AI provider).
- Outgoing webhooks — fire a real POST to your URL on `booking.created` / `payment.confirmed`, with a "Send Test" button.
- API docs on the API Manager page are generated from the same route list the REST API registers from — they can't drift out of sync.

**Explicitly not built (marked "Coming Soon" in the dashboard, not hidden):**
- CRM integration — no specific CRM was named, so there's nothing to connect to yet. Tell your developer which CRM and this becomes a scoped, buildable feature.
- Automatic image analysis of booking photos — the AI Configuration screen has the settings for this (image analysis prompt/toggle), but nothing calls it yet.

## Extending / maintaining

- Nearly everything is editable from the dashboard — pricing, sessions, Stripe keys, email copy, form HTML/CSS/JS, schedule, AI provider, webhooks. No code editing needed for day-to-day changes.
- The plugin prefix is `wise_` throughout (options, hooks, DB tables, shortcode, REST namespace `wise/v1`).
- Existing installs upgrade in place — new DB columns, options, and sessions data migrate automatically the next time the plugin loads after a file update (see the install note above about deleting the old copy first).
