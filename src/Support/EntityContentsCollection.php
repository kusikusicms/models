<?php

namespace KusikusiCMS\Models\Support;

use Illuminate\Database\Eloquent\Collection;
use KusikusiCMS\Models\EntityContent;

/**
 * @extends Collection<int, EntityContent>
 */
class EntityContentsCollection extends Collection
{
    /**
     * Flatten rawContents to an associative array keyed by field: value is text for the current language.
     *
     * @return array<string, string>
     */
    public function flattenByField(): array
    {
        return $this->reduce(function (array $carry, EntityContent $content) {
            $carry[$content->field] = $content->text;

            return $carry;
        }, []);
    }

    /**
     * Group rawContents by field, each containing an associative array of lang => text.
     *
     * @return array<string, array<string, string>>
     */
    public function groupByField(): array
    {
        return $this->reduce(function (array $carry, EntityContent $content) {
            if (! isset($carry[$content->field])) {
                $carry[$content->field] = [];
            }
            $carry[$content->field][$content->lang] = $content->text;

            return $carry;
        }, []);
    }

    /**
     * Group rawContents by language, each containing an associative array of field => text.
     *
     * @return array<string, array<string, string>>
     */
    public function groupByLang(): array
    {
        return $this->reduce(function (array $carry, EntityContent $content) {
            if (! isset($carry[$content->lang])) {
                $carry[$content->lang] = [];
            }
            $carry[$content->lang][$content->field] = $content->text;

            return $carry;
        }, []);
    }
}
