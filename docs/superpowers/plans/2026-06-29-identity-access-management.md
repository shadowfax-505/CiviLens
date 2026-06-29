# Identity & Access Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build CivicLens Sprint 01 Identity & Access Management as the security foundation for future modules.

**Architecture:** Extend the current Laravel 13 modular monolith without replacing the custom role and permission foundation. Keep controllers thin by using request classes, policies, and focused services/actions for user state changes and account activity logging.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS, existing custom roles/permissions, PHPUnit/Pest-compatible Laravel feature tests, SQLite test database, Vite.

---

### Task 1: IAM Schema and Models

**Files:**
- Create: `database/migrations/2026_06_29_000002_extend_users_for_identity_management.php`
- Create: `database/migrations/2026_06_29_000003_create_permission_groups_table.php`
- Create: `database/migrations/2026_06_29_000004_create_account_activities_table.php`
- Create: `app/Models/PermissionGroup.php`
- Create: `app/Models/AccountActivity.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Permission.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] Write failing tests for profile fields, account activity, active/locked state, and permission groups.
- [ ] Implement migrations, models, casts, relationships, and seed data.
- [ ] Run targeted model tests and then full test suite.

### Task 2: Authentication Flows

**Files:**
- Create: `app/Http/Controllers/Auth/*`
- Create: `app/Http/Requests/Auth/*`
- Create: `app/Services/Identity/AccountActivityLogger.php`
- Create: auth Blade views under `resources/views/auth/`
- Modify: `routes/web.php`

- [ ] Write failing tests for registration, login, logout, remember me, forgot password, reset password, email verification, and password confirmation.
- [ ] Implement controllers, requests, views, and routes.
- [ ] Run targeted auth tests and then full test suite.

### Task 3: Profile Management

**Files:**
- Create: `app/Http/Controllers/ProfileController.php`
- Create: `app/Http/Requests/Profile/*`
- Create: profile Blade views under `resources/views/profile/`
- Modify: `routes/web.php`

- [ ] Write failing tests for profile view/edit, avatar upload, password change, notification preferences, and activity list.
- [ ] Implement profile controller, requests, storage handling, and views.
- [ ] Run targeted profile tests and then full test suite.

### Task 4: Admin User Management

**Files:**
- Create: `app/Http/Controllers/Admin/UserController.php`
- Create: `app/Http/Controllers/Admin/UserRoleController.php`
- Create: `app/Http/Requests/Admin/*`
- Create: `app/Policies/UserPolicy.php`
- Create: admin Blade views under `resources/views/admin/users/`
- Modify: `routes/web.php`

- [ ] Write failing tests for admin-only access, listing, search, filters, sorting, pagination, activation, lock, role assignment, and password reset.
- [ ] Implement policies, controllers, requests, queries, and views.
- [ ] Run targeted admin tests and then full test suite.

### Task 5: Documentation and Verification

**Files:**
- Modify: `docs/04_DATABASE_BIBLE.md`
- Modify: `docs/08_SECURITY_CONSTITUTION.md`
- Modify: `docs/09_API_SPECIFICATION.md`
- Create: `docs/modules/identity/README.md`
- Modify: `.ai/PROJECT_MEMORY.md`
- Modify: `.ai/CURRENT_SPRINT.md`
- Modify: `.ai/NEXT_STEPS.md`
- Modify: `CHANGELOG.md`

- [ ] Update docs to match the implemented IAM module.
- [ ] Run `composer test`.
- [ ] Run `npm run build`.
- [ ] Provide final summary and suggested commit message.

