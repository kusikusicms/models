# Configuration

All package settings live under `kusikusicms.models.*`. You can publish a local copy with:

```
php artisan vendor:publish --tag=kusikusicms-config
```

Default config (`config/kusikusicms/models.php`):
```
return [
    'short_id_length' => 10,
    'short_id_max_attempts' => 5,
    'store_versions' => true,
    'default_language' => 'en',
];
```

## Keys

- `short_id_length` (int)
  - Length of generated short IDs for models using the `UsesShortId` trait.
  - Capped internally to the DB column length (26 by default).

- `short_id_max_attempts` (int)
  - Maximum number of retries to avoid collisions when generating short IDs.
  - An exception is thrown after exceeding this limit.

- `default_language` (string)
  - Default language for content helpers and scopes when none is provided.

- `store_versions` (bool)
  - Reserved for archives/versioning. Defaults to `true` and may be used by related features.

## Reading configuration

```
config('kusikusicms.models.default_language');
config('kusikusicms.models.short_id_length');
```

## Notes
- The package standardizes on the dot-notation namespace: `kusikusicms.models.*`.
- Scopes like `orderByContent()` rely on `default_language` when a language is not passed explicitly.