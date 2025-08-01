<?php

declare(strict_types=1);

namespace App\Enums;

enum TranscriptionStatus: string
{
    case PENDING = 'P';
    case IN_PROGRESS = 'I';
    case SUCCESS = 'S';
    case FAILED = 'F';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
        };
    }

    public function canBeResubmitted(): bool
    {
        return $this === self::FAILED;
    }
}
