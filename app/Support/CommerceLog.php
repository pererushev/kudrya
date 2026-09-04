<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

final class CommerceLog
{
    public static function event(string $outcome, array $context = []): void
    {
        Log::channel('commerce')->info($outcome, $context);
    }
}
