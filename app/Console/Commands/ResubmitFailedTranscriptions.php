<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TranscriptionStatus;
use App\Jobs\ResubmitFailedTranscription;
use App\Models\AudioTranscription;
use App\Traits\SendsConsoleOutputToLogs;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ResubmitFailedTranscriptions extends Command
{
    use SendsConsoleOutputToLogs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transcriptions:resubmit-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resubmit failed transcriptions to the Whisper API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->infoConsoleOutputAndLog('Resubmitting failed transcriptions...');

        $job = $this;

        // Use chunking to avoid loading all failed transcriptions into memory at once
        AudioTranscription::where('status', TranscriptionStatus::FAILED)
            ->chunk(100, static function (Collection $transcriptions) use ($job): void {
                /** @var Collection<int, AudioTranscription> $transcriptions */
                foreach ($transcriptions as $transcription) {
                    $job->info("Dispatching resubmission job for transcription ID: {$transcription->id}");

                    // Dispatch a job to resubmit each failed transcription
                    ResubmitFailedTranscription::dispatch($transcription->id);
                }
            });

        $this->infoConsoleOutputAndLog('All failed transcriptions have been queued for resubmission.');

        return Command::SUCCESS;
    }
}
