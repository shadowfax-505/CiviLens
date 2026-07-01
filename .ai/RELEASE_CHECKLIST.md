# Release Checklist

- [ ] `composer validate --strict` passes.
- [ ] `php artisan test` passes.
- [ ] `vendor/bin/pint --test` passes.
- [ ] `vendor/bin/phpstan analyse` passes.
- [ ] `vendor/bin/rector process --dry-run` passes.
- [ ] `npm run build` passes.
- [ ] `npm run test:e2e` passes or blocker is documented.
- [ ] `git diff --check` passes.
- [ ] `php artisan config:cache`, `route:cache`, and `view:cache` pass, followed by `optimize:clear`.
- [ ] Migrations and rollback plan reviewed.
- [ ] Changelog and docs updated.
- [ ] `/healthz`, `/version`, and authorized `/admin/system/metrics` verified.
- [ ] Queue workers, scheduler, backups, and search indexes verified for production.
