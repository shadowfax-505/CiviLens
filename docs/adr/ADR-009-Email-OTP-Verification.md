# ADR-009: Email OTP Verification With Signed-Link Fallback

## Status

Accepted

## Decision

CivicLens sends a short-lived, one-time six-digit email OTP after registration while retaining Laravel's signed email-verification link as a fallback. OTP values are stored only as hashes, expire after ten minutes, allow five verification attempts, and are deleted after successful verification.

## Consequences

The system provides an accessible code-entry flow without replacing Laravel's established signed-link mechanism. It adds the additive `email_verification_otps` table and queued mail delivery. Password reset continues to use Laravel's reset-token flow.
