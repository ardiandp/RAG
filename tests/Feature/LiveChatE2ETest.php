<?php

namespace Tests\Feature;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiveChatE2ETest extends TestCase
{
    use RefreshDatabase;

    /**
     * Live end-to-end test: routes through the HTTP kernel against the
     * real PostgreSQL database and the real local Ollama (Qwen).
     *
     * Skipped unless OLLAMA_LIVE_TEST=true is set, so the normal test
     * suite stays deterministic.
     */
    #[Test]
    public function chat_endpoint_answers_against_real_ollama(): void
    {
        if (getenv('OLLAMA_LIVE_TEST') !== 'true') {
            $this->markTestSkipped('Set OLLAMA_LIVE_TEST=true untuk menjalankan E2E live.');
        }

        $response = $this->postJson('/api/chat', [
            'message' => 'Jawab dalam satu kalimat: Apa itu PostgreSQL?',
        ]);

        $response->assertOk();
        $answer = $response->json('answer');

        $this->assertIsString($answer);
        $this->assertNotEmpty($answer);
        $this->assertStringContainsStringIgnoringCase('postgres', $answer);

        $conversation = Conversation::with('messages')->sole();
        $this->assertSame(['user', 'assistant'], $conversation->messages->pluck('role')->all());
    }
}
