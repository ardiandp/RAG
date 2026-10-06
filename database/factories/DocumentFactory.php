<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'knowledge_source_id' => KnowledgeSource::factory(),
            'title' => fake()->words(4, true),
            'content' => fake()->paragraphs(5, true),
            'status' => 'indexed',
        ];
    }
}
