<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
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
    public function unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/api/chat', ['message' => 'Halo'])->assertUnauthorized();
    }

    #[Test]
    public function it_returns_an_answer_and_records_the_exchange(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->fakeOllamaAnswer('PostgreSQL adalah database relasional.');

        $response = $this->postJson('/api/chat', ['message' => 'Apa itu PostgreSQL?']);

        $response->assertOk()
            ->assertJson([
                'answer' => 'PostgreSQL adalah database relasional.',
            ]);

        $conversation = Conversation::with('messages')->sole();
        $this->assertSame(['user', 'assistant'], $conversation->messages->pluck('role')->all());
        $this->assertSame('Apa itu PostgreSQL?', $conversation->messages[0]->content);
        $this->assertSame(auth()->id(), $conversation->user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agent_run.started']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agent_run.finished']);
    }

    #[Test]
    public function it_continues_an_existing_conversation(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);
        $this->fakeOllamaAnswer('Baik Budi.');

        $conversation = Conversation::create(['title' => 'Chat Budi', 'user_id' => $user->id]);

        $response = $this->postJson('/api/chat', [
            'message' => 'Nama saya Budi',
            'conversation_id' => $conversation->id,
        ]);

        $response->assertOk()->assertJson(['answer' => 'Baik Budi.']);

        $this->assertCount(2, $conversation->fresh()->messages);
    }

    #[Test]
    public function it_rejects_a_conversation_owned_by_another_user(): void
    {
        $attacker = User::factory()->create(['role' => 'user']);
        $owner = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($attacker);

        $conversation = Conversation::create(['title' => 'Punya orang lain', 'user_id' => $owner->id]);

        $this->postJson('/api/chat', [
            'message' => 'Halo',
            'conversation_id' => $conversation->id,
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Conversation ini milik pengguna lain.');
    }

    #[Test]
    public function it_rejects_an_empty_message(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->postJson('/api/chat', ['message' => '']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    #[Test]
    public function it_returns_502_when_ollama_is_unreachable(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
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
