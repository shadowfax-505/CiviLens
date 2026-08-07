# 08 Security Constitution

## Security Goals

Protect account access, document integrity, audit logs, and administrative actions.

## V1 Requirements

- Laravel authentication.
- Role-based authorization.
- CSRF protection for web forms.
- Sanctum for API tokens.
- Validation for every request.
- Audit logs for sensitive operations.
- No secrets committed to git.
- Public health checks must be readiness-only and must not expose secrets, credentials, internal paths, or environment dumps.
- Civic intelligence indicators must remain advisory, evidence-backed, and human-reviewed; CivicLens must never make legal accusations.

## Implemented in Sprint 01

- Registration, login, logout, password reset, email verification, and password confirmation routes.
- Current-password validation for password changes.
- Account active/locked enforcement through middleware.
- User policy for administrator-only user management.
- Account activity logging for security-relevant identity events.
- Administrator controls for activation, locking, role assignment, and password reset.

## API Auth Status

Sanctum is still the documented API authentication target. Installing `laravel/sanctum` was blocked by the execution environment usage limit during Sprint 01, so token-based API auth is not yet implemented.

## Sprint 13 Part 1 Security Notes

`/healthz` is intentionally public-safe. It reports component readiness only.

Manual Civic Integrity Engine execution is protected by the existing `intelligence.manage` authorization path. Generated indicators inherit the existing evidence, search visibility, and human review controls.

## Sprint 13 Part 2 Security Notes

`/version` exposes only deploy-safe metadata and must not leak secrets, raw configuration, connection strings, or internal storage paths.

`/admin/system/metrics` is protected by analytics authorization. It may summarize queue, scheduler, cache, database, and integrity-run state for operators, but it must not publish credentials, SQL connection details, filesystem paths, or personally sensitive records.

Rule management is protected by intelligence authorization. All rule configuration changes must pass through validation and write `intelligence_rule_audits`; direct production database editing is prohibited.

`RequestCorrelation` attaches a request ID to responses and log context for incident review without exposing private user data.

Release Candidate 1 adds baseline defensive response headers on all application responses:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Strict-Transport-Security` in production environments

## V1.0.0 Deep Scan Remediation

The final deep review confirmed and remediated seven medium/P2 paths without changing public route names: bulk document actions now authorize every selected document atomically; citizen report tracking requires the submitter/moderator policy and streams attachments through the protected route; public tender documents reuse the public visibility query; project budgets and contractor profiles enforce child publication/lifecycle state; public search is throttled and bounded to a 120-character query and 1,000 indexed rows.

Thirteen additional candidates remain explicit follow-up items in the scan coverage (cross-tender foreign-key binding, final-administrator races, cookie/HTTPS provider configuration, analytics retention, metrics permissions, health metadata, and change-request upload deployment preconditions). They were not silently suppressed or promoted without the fixture or deployment evidence required to validate them safely.

## Source Acquisition Egress Controls

`ApprovedSourceUrlGuard` resolves an approved host and `SafeHttpTransport` pins that resolved address through `CURLOPT_RESOLVE`, so the address checked is the address contacted. Two defects broke that guarantee and are now closed.

The guard canonicalized the host for the allowlist check and DNS resolve but returned the caller's original URL, so a trailing dot, uppercase host, or explicit `:443` left the pin entry and the request URL disagreeing — cURL treated them as different hosts, ignored the pin, and resolved independently. `ValidatedSourceUrl` now carries a canonical URL and the validated port, and the pin is built from the same values used for the request. Non-ASCII hosts are rejected outright, because cURL punycodes after validation and would produce the same mismatch.

Pinning also failed open: when `CURLOPT_RESOLVE` was undefined or `pin_resolved_address` was false, the request was sent unpinned with no signal. It is now fail-closed — `UnsafeSourceUrl` is thrown and nothing leaves the process. `INGESTION_ALLOW_UNPINNED_EGRESS` (default `false`) is the only way to proceed unpinned, and it logs a warning on every request. Egress failures stay hard failures and never become quarantine; quarantine remains reserved for malware-scan outcomes, so a redirected response is never written to storage.

## V2 Expansion Notes

Add threat modeling for public APIs, rate limiting by tier, data provenance signatures, and model governance controls.
