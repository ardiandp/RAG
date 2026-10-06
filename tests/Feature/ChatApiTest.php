<?php

namespace Tests\Feature;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOllamaAnswer(string $answer): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => $answer],
                'done' => true,
            ]),
        ]);
    }

    #[Test]
    public function it_returns_an_answer_and_records_the_exchange(): void
    {
        $this->fakeOllamaAnswer('PostgreSQL adalah database relasional.');

        $response = $this->postJson('/api/chat', ['message' => 'Apa itu PostgreSQL?']);

        $response->assertOk()
            ->assertJson([
                'answer' => 'PostgreSQL adalah database relasional.',
            ]);

        $conversation = Conversation::with('messages')->sole();
        $this->assertSame(['user', 'assistant'], $conversation->messages->pluck('role')->all());
        $this->assertSame('Apa itu PostgreSQL?', $conversation->messages[0]->content);
    }

    #[Test]
    public function it_continues_an_existing_conversation(): void
    {
        $this->fakeOllamaAnswer('Baik Budi.');

        $conversation = Conversation::create(['title' => 'Chat Budi']);

        $response = $this->postJson('/api/chat', [
            'message' => 'Nama saya Budi',
            'conversation_id' => $conversation->id,
        ]);

        $response->assertOk()->assertJson(['answer' => 'Baik Budi.']);

        $this->assertCount(2, $conversation->fresh()->messages);
    }

    #[Test]
    public function it_rejects_an_empty_message(): void
    {
        $response = $this->postJson('/api/chat', ['message' => '']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    #[Test]
    public function it_returns_502_when_ollama_is_unreachable(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Failed to connect.');
            },
        ]);

        $response = $this->postJson('/api/chat', ['message' => 'Halo']);

        $response->assertStatus(502)
            ->assertJsonPath('message', 'Tidak dapat terhubung ke Ollama di http://127.0.0.1:11434. Pastikan Ollama sedang berjalan.');
    }
}
