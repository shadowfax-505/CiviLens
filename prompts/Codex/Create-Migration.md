# Create Migration Prompt

Create a Laravel migration for `[TABLE]`.

Rules:

- Use clear column names.
- Use `foreignId()->constrained()` where appropriate.
- Add indexes for common filters.
- Avoid breaking existing data.
- Update `docs/database/SchemaOverview.md`.

