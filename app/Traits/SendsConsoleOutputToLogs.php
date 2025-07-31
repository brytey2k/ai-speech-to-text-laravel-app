<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * @phpstan-require-extends Command
 */
trait SendsConsoleOutputToLogs
{
    /**
     * @param string $content
     * @param array<string|int, mixed> $context
     *
     * @return void
     */
    public function infoConsoleOutputAndLog(string $content, array $context = []): void
    {
        Log::info($content, $context);
        $this->info($content);
    }

    /**
     * @param string $content
     * @param array<string|int, mixed> $context
     *
     * @return void
     */
    public function errorConsoleOutputAndLog(string $content, array $context = []): void
    {
        Log::error($content, $context);
        $this->error($content);
    }
}
