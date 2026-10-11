<?php

namespace KusikusiCMS\Models\Traits;

use Illuminate\Support\Facades\Config;
use KusikusiCMS\Models\Support\IdGenerator;
use KusikusiCMS\Models\Support\ShortIdGenerator;

trait UsesShortId
{
    /**
     * The "booting" method of the model. Automatically generates a short string id for new models.
     */
    public static function bootUsesShortId(): void
    {
        self::creating(function ($model) {
            $keyName = $model->getKeyName();
            $provided = $model->getAttribute($keyName);
            $maxLen = (int) Config::get('kusikusicms.models.short_id_length', 10);
            // Safety cap against DB column length (26 in default migration)
            $maxLen = max(1, min($maxLen, 26));

            if (empty($provided)) {
                $attempts = 0;
                $maxAttempts = (int) Config::get('kusikusicms.models.short_id_max_attempts', 5);

                // Resolve the ID generator from the container; fallback to default implementation
                $app = function_exists('app') ? app() : null;
                $generator = $app && $app->bound(IdGenerator::class)
                    ? $app->make(IdGenerator::class)
                    : new ShortIdGenerator;

                do {
                    $attempts++;
                    $id = $generator->generate($maxLen);
                    $exists = static::query()->where($keyName, $id)->exists();
                    if (! $exists) {
                        $model->setAttribute($keyName, $id);
                        break;
                    }
                } while ($attempts < $maxAttempts);

                if (empty($model->getAttribute($keyName))) {
                    throw new \RuntimeException('Failed to generate a unique short ID after '
                        .$maxAttempts.' attempts.');
                }
            } else {
                $model->setAttribute($keyName, substr((string) $provided, 0, $maxLen));
            }
        });
    }

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return 'id';
    }
}
