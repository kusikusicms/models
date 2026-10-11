<?php

namespace KusikusiCMS\Models\Support;

use PUGX\Shortid\Shortid;

class ShortIdGenerator implements IdGenerator
{
    public function generate(int $length): string
    {
        return Shortid::generate($length);
    }
}
