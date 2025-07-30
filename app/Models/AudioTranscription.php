<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $file_path
 * @property string|null $transcription
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|AudioTranscription newModelQuery()
 * @method static Builder<static>|AudioTranscription newQuery()
 * @method static Builder<static>|AudioTranscription query()
 * @method static Builder<static>|AudioTranscription whereCreatedAt($value)
 * @method static Builder<static>|AudioTranscription whereFilePath($value)
 * @method static Builder<static>|AudioTranscription whereId($value)
 * @method static Builder<static>|AudioTranscription whereTranscription($value)
 * @method static Builder<static>|AudioTranscription whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class AudioTranscription extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'file_path',
        'transcription',
    ];
}
