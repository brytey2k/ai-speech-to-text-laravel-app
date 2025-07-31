<?php

declare(strict_types=1);

use App\Enums\TranscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audio_transcriptions', static function (Blueprint $table) {
            $table->char('status', 1)
                ->default(TranscriptionStatus::PENDING->value)
                ->after('transcription');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_transcriptions', static function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
