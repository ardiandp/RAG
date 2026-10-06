<?php

namespace Tests\Feature;

use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    #[Test]
    public function it_generates_embedding_for_a_single_text(): void
    {
        Http::fake([
            '*/api/embed' => Http::response(['embeddings' => [[0.1, 0.2, 0.3]]]),
        ]);

        $embedding = $this->service()->embed('halo');

        $this->assertSame([0.1, 0.2, 0.3], $embedding);
    }

    #[Test]
    public function it_generates_embeddings_for_multiple_texts(): void
    {
        Http::fake([
            '*/api/embed' => Http::response(['embeddings' => [[0.1], [0.2], [0.3]]]),
        ]);

        $embeddings = $this->service()->embedMany(['a', 'b', 'c']);

        $this->assertCount(3, $embeddings);
        $this->assertSame([[0.1], [0.2], [0.3]], $embeddings);
    }

    #[Test]
    public function it_sends_model_and_input_to_ollama(): void
    {
        Http::fake(['*/api/embed' => Http::response(['embeddings' => [[0.0]]])]);

        $this->service()->embed('teks contoh');

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:11434/api/embed'
            && $request['model'] === 'nomic-embed-text'
            && $request['input'] === ['teks contoh']);
    }

    #[Test]
    public function it_throws_when_ollama_unreachable(): void
    {
        Http::fake(['*/api/embed' => Http::response([], 503)]);

        $this->expectException(RuntimeException::class);

        $this->service()->embed('teks');
    }

    private function service(): EmbeddingService
    {
        return new EmbeddingService('http://127.0.0.1:11434', 'nomic-embed-text', 120);
    }
}
