# Changelog — KusikusiCMS Models

All notable changes to this package will be documented in this file.

## 12.0.0-alpha.2 — 2025-12-28

- Refined `whereContent` scope semantics:
  - Always compares `field = :field` and applies the operator to `text` only
  - Uses `whereHas` instead of joins to avoid alias collisions and hydration side-effects
  - Language resolution: `null` → default config, `''` → any language
  - LIKE values auto-wrapped with `%` when no wildcard is provided
- Tests updated to assert the corrected behavior (previous spec is now active)
- Documentation updated (README and docs/models-and-scopes.md) to reflect operators and language handling
- Performance: added composite index on `(field, lang)` to `entities_contents`
- About version updated to 12.0.0-alpha.2

## 12.0.0-alpha.1 — 2025-12-28

Initial Laravel 12–ready alpha with the following highlights:

- Config & Provider hygiene
  - Standardized config namespace to `kusikusicms.models.*`
  - About entry visible via `php artisan about`

- Entity model modernization
  - Laravel 12 `Attribute::make` accessor for `status`
  - Safer mass-assignment via guarded system fields
  - Typed scope signatures

- Traits & Collections
  - `UsesShortId` now uses container-resolved `IdGenerator` with configurable length and collision retries
  - Collections include typed PHPDoc and chainable helpers for content transformations

- Migrations (fresh installs)
  - Timezone-aware timestamps (`timestampsTz`, `softDeletesTz`) and `timestampTz` for publish windows
  - Modern foreign key helpers and cascades

- Factories & Seeder
  - Factory states: `draft`, `scheduled`, `live`, `expired`
  - `withContents` helper for quick content attachment
  - Demo seeder: `KusikusiCMS\Models\Database\Seeders\ModelsSeeder`

- Events
  - `$dispatchesEvents` retained so host apps can listen to lifecycle events

- Testing & CI
  - Package test suite using Orchestra Testbench (SQLite in-memory)
  - Host app integration tests under `apps/starter`
  - CI workflow runs both suites

- Documentation
  - README with quickstart and examples
  - docs/: installation, configuration, models & scopes, events, factories & seeding, testing, id-generator, design-decisions

Notes:
- `whereContent` scope behavior is characterized by tests; a future update will align it with the intended operator-on-text-only semantics.
- Relation scope aliases currently use dot-style (e.g., `parent.position`); a future update may switch to snake_case with full `entities.*` hydration.
