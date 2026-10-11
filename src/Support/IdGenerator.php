<?php

namespace KusikusiCMS\Models\Support;

interface IdGenerator
{
    /**
     * Generate an ID string with the given maximum length.
     */
    public function generate(int $length): string;
}
