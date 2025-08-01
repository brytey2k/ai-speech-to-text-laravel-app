<?php

declare(strict_types=1);

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
            $table->index('status', 'idx_audio_transcriptions_status');
            $table->index('created_at', 'idx_audio_transcriptions_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_transcriptions', static function (Blueprint $table) {
            $table->dropIndex('idx_audio_transcriptions_status');
            $table->dropIndex('idx_audio_transcriptions_created_at');
        });
    }
};
