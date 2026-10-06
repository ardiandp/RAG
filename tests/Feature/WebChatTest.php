<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebChatTest extends TestCase
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
    public function the_chat_page_requires_login(): void
    {
        $this->get('/dashboard/chat')->assertRedirect('/login');
    }

    #[Test]
    public function the_chat_page_renders(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get('/dashboard/chat')
            ->assertOk()
            ->assertSee('Mulai percakapan baru dengan agent IRNIS.');
    }

    #[Test]
    public function a_message_creates_a_conversation_and_returns_the_answer(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->fakeOllamaAnswer('Total penjualan September adalah Rp 250.000.000.');

        $response = $this->actingAs($user)->postJson('/dashboard/chat', [
            'message' => 'Berapa total penjualan September?',
        ]);

        $response->assertOk()
            ->assertJsonPath('answer', 'Total penjualan September adalah Rp 250.000.000.')
            ->assertJsonStructure(['conversation_id', 'status', 'steps']);

        $conversation = Conversation::sole();
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertSame(['user', 'assistant'], $conversation->messages->pluck('role')->all());
    }

    #[Test]
    public function it_continues_a_conversation_owned_by_the_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->fakeOllamaAnswer('Baik Budi.');
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'title' => 'Chat Budi']);

        $this->actingAs($user)->postJson('/dashboard/chat', [
            'message' => 'Nama saya Budi',
            'conversation_id' => $conversation->id,
        ])->assertOk()->assertJsonPath('answer', 'Baik Budi.');

        $this->assertCount(2, $conversation->fresh()->messages);
    }

    #[Test]
    public function it_rejects_a_conversation_owned_by_another_user(): void
    {
        $attacker = User::factory()->create(['role' => 'user']);
        $owner = User::factory()->create(['role' => 'user']);
        $this->fakeOllamaAnswer('tidak dipakai');

        $conversation = Conversation::factory()->create(['user_id' => $owner->id, 'title' => 'Punya orang lain']);

        $this->actingAs($attacker)->postJson('/dashboard/chat', [
            'message' => 'Halo',
            'conversation_id' => $conversation->id,
        ])->assertStatus(403);

        $this->actingAs($attacker)->get('/dashboard/chat/'.$conversation->id)->assertForbidden();
    }

    #[Test]
    public function the_chat_thread_page_is_scoped_to_own_conversations(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'title' => 'Milik saya']);

        $this->actingAs($user)
            ->get('/dashboard/chat/'.$conversation->id)
            ->assertOk()->assertSee('Milik saya');
    }
}
