<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AudioTranscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AudioTranscription>
 */
class AudioTranscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AudioTranscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_path' => 'speech_segments/' . $this->faker->uuid() . '.mp3',
            'transcription' => $this->faker->optional(0.7)->paragraph(),
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'updated_at' => fn(array $attributes) => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
        ];
    }

    /**
     * Indicate that the transcription is completed.
     *
     * @return static
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'transcription' => $this->faker->paragraph(),
        ]);
    }

    /**
     * Indicate that the transcription is pending.
     *
     * @return static
     */
    public function pending(): static
    {
        return $this->state(static fn(array $attributes) => [
            'transcription' => null,
        ]);
    }
}
