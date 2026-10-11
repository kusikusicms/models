# ID Generator

This package assigns short string IDs to models via the `UsesShortId` trait. By default, it uses `PUGX\Shortid` wrapped by a small adapter. You can replace the generator in your host application.

## How it works
- `UsesShortId` listens to the `creating` event and sets the primary key when missing.
- The length and retry behavior are controlled by configuration:
  - `kusikusicms.models.short_id_length`
  - `kusikusicms.models.short_id_max_attempts`
- Internally, it resolves an `IdGenerator` from the container; defaults to `ShortIdGenerator` if none is bound.

```
namespace KusikusiCMS\Models\Support;

interface IdGenerator
{
    public function generate(int $length): string;
}
```

## Override the generator

Create your own implementation:
```
namespace App\Support;

use KusikusiCMS\Models\Support\IdGenerator;

class NanoIdGenerator implements IdGenerator
{
    public function generate(int $length): string
    {
        // Implement with your preferred lib
        return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(48))), 0, $length);
    }
}
```

Bind it in a service provider:
```
use App\Support\NanoIdGenerator;
use Illuminate\Support\ServiceProvider;
use KusikusiCMS\Models\Support\IdGenerator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IdGenerator::class, NanoIdGenerator::class);
    }
}
```

Now all models using `UsesShortId` will get IDs from your generator.

## Notes
- The trait caps generated ID length to the DB column length (26 by default in migrations).
- On collision, it retries up to `short_id_max_attempts` and throws a `RuntimeException` if it cannot find a unique ID.
