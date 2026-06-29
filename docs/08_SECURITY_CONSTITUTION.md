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

## Implemented in Sprint 01

- Registration, login, logout, password reset, email verification, and password confirmation routes.
- Current-password validation for password changes.
- Account active/locked enforcement through middleware.
- User policy for administrator-only user management.
- Account activity logging for security-relevant identity events.
- Administrator controls for activation, locking, role assignment, and password reset.

## API Auth Status

Sanctum is still the documented API authentication target. Installing `laravel/sanctum` was blocked by the execution environment usage limit during Sprint 01, so token-based API auth is not yet implemented.

## V2 Expansion Notes

Add threat modeling for public APIs, rate limiting by tier, data provenance signatures, and model governance controls.
