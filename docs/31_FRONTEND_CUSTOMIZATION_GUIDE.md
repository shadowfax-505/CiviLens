# 31 Frontend Customization Guide

## Purpose

CivicLens v1 uses Laravel Blade, Tailwind 4, and Vite as the production frontend layer. The frontend can be refined after deployment, but visual edits must not change backend contracts, route names, policies, queues, Docker asset behavior, or database schemas unless a sprint explicitly requires it.

## Safe Customization Rules

- Prefer shared Blade components and `resources/css/app.css` design tokens before editing many domain views.
- Keep form field names, HTTP methods, route names, authorization-aware navigation, and browser-test button labels intact.
- Do not add fake analytics, placeholder modules, unimplemented actions, OCR, LLMs, embeddings, semantic search, or autonomous AI workflows.
- Keep `@vite(['resources/css/app.css', 'resources/js/app.js'])` in the application shell so Docker app and Nginx images share the same immutable build manifest.
- Use the profile appearance setting for light, dark, and system modes. It is stored in `users.notification_preferences.appearance` and does not require a migration.
- Validate visual changes with browser tests at desktop and mobile widths before release.

## Design Direction

The CivicLens v1 interface should feel like a civic intelligence command center: classy, dense, transparent, audit-first, and restrained. Use deep navy, teal, gold, slate, white, and high-contrast dark surfaces. Public pages should emphasize transparency and evidence; admin pages should emphasize scanning, filtering, review state, and operational decisions.

## Deployment Notes

Docker remains the canonical production target. Vercel can be evaluated through container services. Netlify is suitable for static public previews or reverse-proxy use unless a future ADR approves a separated frontend architecture.
