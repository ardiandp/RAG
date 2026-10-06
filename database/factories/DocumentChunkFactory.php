<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dimensions = (int) config('ollama.embedding_dimensions', 768);

        return [
            'document_id' => Document::factory(),
            'chunk_index' => fake()->numberBetween(0, 20),
            'content' => fake()->paragraph(),
            'token_count' => fake()->numberBetween(10, 120),
            'embedding' => $this->randomVector($dimensions),
        ];
    }

    /**
     * @return string pgvector literal seperti "[0.1,0.2,...]"
     */
    private function randomVector(int $dimensions): string
    {
        $values = array_map(
            fn () => round((fake()->randomFloat(2, -1, 1)) + 0.0, 4),
            range(1, $dimensions)
        );

        return '['.implode(',', $values).']';
    }
}
