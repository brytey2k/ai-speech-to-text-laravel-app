<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

class UuidGenerator
{
    public function generate(): string
    {
        return Str::uuid7()->toString();
    }
}
