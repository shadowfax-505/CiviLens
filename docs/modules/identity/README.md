# Identity & Access Management Module

## Responsibility

The Identity module owns authentication, account security, profile management, role assignment, permission assignment, and account activity logging.

## Implemented Web Features

- Registration with default citizen role assignment.
- Login with remember-me support and rate limiting.
- Logout with session invalidation.
- Forgot password and password reset.
- Email verification by six-digit OTP with signed-link fallback.
- Password confirmation routes.
- Profile view and update.
- Avatar upload to the public disk.
- Password change with current-password validation.
- Notification preferences.
- Approved-change category preferences with major changes enabled by default.
- Saved district-ID preferences without precise browser-location retention.
- Account activity list.
- Administrator user list with search, status filtering, sorting, pagination, eager-loaded roles, and account actions.
- Administrator user creation with temporary passwords, role assignment, and unverified-by-default onboarding.
- Activate/deactivate accounts.
- Lock/unlock accounts.
- Assign and remove roles.
- Administrator-set temporary passwords.
- Staff request queue for correction submissions; administrators review but edit source records directly.

## Tables

- `users`
- `roles`
- `permissions`
- `permission_groups`
- `role_user`
- `role_permission`
- `account_activities`
- `password_reset_tokens`
- `sessions`
- `email_verification_otps`
- `user_district_preferences`
- `user_notification_preferences`

## Authorization

Authorization uses Laravel policies and the existing custom role system. `UserPolicy` allows administrators to view, create, and administer users. Account status is enforced through the `active` middleware, including email-verification routing for unverified users.

Registration sends an OTP and signed verification link. OTP values are hashed, expire after ten minutes, allow five attempts, and are removed after successful verification. Password reset remains Laravel's time-limited reset-link flow.

## API Status

The web IAM module is implemented. Token-based API authentication remains blocked until `laravel/sanctum` can be installed; the package installation was blocked by the execution environment usage limit during this sprint.

## V2 Notes

V2 now provides distinct citizen, staff, and administrator experience shells over the existing policy boundary, plus district and notification preferences. Organization-scoped roles, API access tiers, public dataset tokens, and richer security event analytics remain future work.
