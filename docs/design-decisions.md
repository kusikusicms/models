# Design decisions

This document highlights key choices in the KusikusiCMS Models package and the rationale behind them.

## 1) `$dispatchesEvents` for lifecycle notifications
- We keep `$dispatchesEvents` on the `Entity` model so host applications can listen to strongly-typed domain events using Laravel's standard event system.
- This enables decoupled consumers (listeners, queues) without coupling business logic to the model.
- Observer-based lifecycle logic remains optional and can be enabled by registering an observer in a service provider.

## 2) Short string IDs instead of auto-increment integers
- Rationale: portability (easier merges/seeding), obscurity (less predictable), and suitability for distributed systems.
- Implemented via the `UsesShortId` trait.
- The generator is injectable (`IdGenerator` interface) for testability and custom strategies.
- Safety considerations: capped length, collision retries with explicit exception on exhaustion.

## 3) Timezone-aware migrations & modern FK helpers
- We adopt `timestampsTz()`/`softDeletesTz()` and `timestampTz()` for publish windows to avoid ambiguity around time zones.
- Use `foreignId()->constrained()` helpers to express intent and proper cascading behavior.

## 4) Scopes for graph and content operations
- Relation scopes (`childrenOf`, `parentOf`, `ancestorsOf`) provide a consistent way to traverse the entity graph.
- Content scopes (`withContents`, `orderByContent`, `whereContent`) offer common querying primitives across languages and fields.
- Known improvement path: normalize join/select aliasing to snake_case and ensure `entities.*` hydration.
- `whereContent` currently supports a flexible signature; we plan to align it with a clearer, operator-on-text-only behavior.

## 5) Collections as transformation helpers
- `EntityCollection` and `EntityContentsCollection` provide convenient transformations for attached contents, producing structures that are easy to render in UIs and APIs.
- They return chainable collections for a fluent style.

## 6) Testing strategy: hybrid (package + host app)
- Package-level tests use Orchestra Testbench for fast, isolated verification of the package contract.
- A minimal integration suite in `apps/sandbox` ensures real-world boot, config, migrations, factories, events, and scopes all work together.
- This hybrid approach balances isolation with end-to-end confidence for consumers.

## 7) Factories and seeding for realistic states
- Factory states (`draft`, `scheduled`, `live`, `expired`) encode common lifecycle scenarios directly into tests and seeds.
- A package seeder demonstrates usage and accelerates manual testing.

## 8) Configuration namespace standardization
- All configuration lives under `kusikusicms.models.*` to avoid key collisions and provide a predictable surface area.

## 9) Future directions
- Consider introducing a string-backed `EntityStatus` enum for stronger typing across the codebase while preserving string values in API responses.
- Normalize aliasing and hydration in join-based scopes and provide upgrade guidance.
- Optional observer: reintroduce and register `EntityObserver` to handle internal tree consistency, keeping domain events as-is for host apps.
