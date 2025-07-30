<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $file_path
 * @property string|null $transcription
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AudioTranscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AudioTranscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AudioTranscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AudioTranscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AudioTranscription whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AudioTranscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AudioTranscription whereTranscription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AudioTranscription whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class AudioTranscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'file_path',
        'transcription',
    ];
}
