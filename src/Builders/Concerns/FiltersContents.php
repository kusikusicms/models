<?php

namespace KusikusiCMS\Models\Builders\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;

trait FiltersContents
{
    /**
     * Language used by the last withContents() call on this query.
     */
    protected ?string $lastUsedLang = null;

    /**
     * Include the contents relation, filtered by options in a dev friendly format.
     *
     * Options:
     * - 'lang'   => string|null Language code to filter by. When null, no lang filter is applied.
     * - 'fields' => string|array|null One field or list of fields to include.
     *
     * @param  array{lang?: string|null, fields?: string|array<int, string>|null}|null  $options
     */
    public function withContents(?array $options = null): static
    {
        $lang = $options['lang'] ?? Config::get('kusikusicms.models.default_language', 'en');
        $this->lastUsedLang = $lang;
        $fields = $options['fields'] ?? null;

        return $this->with(['contentsSupport' => function (Relation $q) use ($lang, $fields) {
            $q->when($lang !== null, function ($q) use ($lang) {
                return $q->where('lang', $lang);
            });
            $q->when($fields !== null, function ($q) use ($fields) {
                if (is_array($fields)) {
                    return $q->whereIn('field', $fields);
                }
                if (is_string($fields)) {
                    return $q->where('field', $fields);
                }

                return $q;
            });
        }]);
    }

    /**
     * Order the result by a content field. Uses the language of the last withContents() call by default.
     */
    public function orderByContent(string $field, string $order = 'asc', ?string $lang = null): static
    {
        if (! $lang) {
            $lang = $this->lastUsedLang ?? Config::get('kusikusicms.models.default_language', 'en');
        }

        return $this->leftJoin("entities_contents as content_order_{$lang}_{$field}", function ($join) use ($field, $lang) {
            $join->on("content_order_{$lang}_{$field}.entity_id", 'entities.id')
                ->where("content_order_{$lang}_{$field}.field", $field)
                ->where("content_order_{$lang}_{$field}.lang", $lang);
        })
            ->orderBy("content_order_{$lang}_{$field}.text", $order);
    }

    /**
     * Filter by a content field.
     *
     * Forms: whereContent($field, $value, $lang = null) or whereContent($field, $operator, $value, $lang = null).
     */
    public function whereContent(string $field, string $param2, ?string $param3 = null, ?string $param4 = null): static
    {
        // Determine overload: operator form or value form
        $opList = ['=', '!=', '<>', 'like', 'not like', 'ilike', 'rlike'];
        if (in_array(strtolower($param2), $opList, true)) {
            $operator = strtolower($param2);
            $value = (string) ($param3 ?? '');
            $lang = $param4;
        } else {
            $operator = '=';
            $value = (string) $param2;
            $lang = $param3;
        }

        if ($lang === null) {
            $lang = $this->lastUsedLang ?? Config::get('kusikusicms.models.default_language', 'en');
        }

        // Normalize LIKE wildcards if caller didn't pass any
        if (in_array($operator, ['like', 'not like', 'ilike'], true) && ! str_contains($value, '%')) {
            $value = "%{$value}%";
        }

        // Use whereHas on relation to avoid alias collisions and ensure field equality
        return $this->whereHas('contentsSupport', function ($q) use ($field, $operator, $value, $lang) {
            $q->where('field', '=', $field)
                ->when($lang !== '', fn ($q2) => $q2->where('lang', $lang))
                ->where('text', $operator, $value);
        });
    }
}
