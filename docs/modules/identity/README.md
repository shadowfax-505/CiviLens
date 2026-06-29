# Identity & Access Management Module

## Responsibility

The Identity module owns authentication, account security, profile management, role assignment, permission assignment, and account activity logging.

## Implemented Web Features

- Registration with default citizen role assignment.
- Login with remember-me support and rate limiting.
- Logout with session invalidation.
- Forgot password and password reset.
- Email verification routes.
- Password confirmation routes.
- Profile view and update.
- Avatar upload to the public disk.
- Password change with current-password validation.
- Notification preferences.
- Account activity list.
- Administrator user list with search, status filtering, sorting, pagination, eager-loaded roles, and account actions.
- Activate/deactivate accounts.
- Lock/unlock accounts.
- Assign and remove roles.
- Administrator-set temporary passwords.

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

## Authorization

Authorization uses Laravel policies and the existing custom role system. `UserPolicy` allows administrators to view and administer users. Account status is enforced through the `active` middleware.

## API Status

The web IAM module is implemented. Token-based API authentication remains blocked until `laravel/sanctum` can be installed; the package installation was blocked by the execution environment usage limit during this sprint.

## V2 Notes

V2 may add organization-scoped roles, API access tiers, public dataset tokens, and richer security event analytics.

