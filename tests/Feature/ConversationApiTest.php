<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ToolCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_the_authenticated_users_conversations(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $own = Conversation::create(['title' => 'Chat Saya', 'user_id' => $user->id]);
        Conversation::create(['title' => 'Chat Orang Lain', 'user_id' => $other->id]);

        $response = $this->getJson('/api/conversations');

        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
        $this->assertSame($own->id, $response->json('data.0.id'));
    }

    #[Test]
    public function unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/conversations')->assertUnauthorized();
    }

    #[Test]
    public function it_returns_the_full_transcript_of_a_run(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $conversation = Conversation::create(['title' => 'Transkrip', 'user_id' => $user->id]);
        Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Berapa penjualan?']);
        Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Total 25 juta.']);

        $run = AgentRun::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'status' => 'success',
            'input' => 'Berapa penjualan?',
            'output' => 'Total 25 juta.',
            'steps' => 3,
            'max_steps' => 5,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        ToolCall::create([
            'agent_run_id' => $run->id,
            'tool_name' => 'get_sales_summary',
            'arguments' => ['month' => '2026-09'],
            'result' => ['total_sales' => 25_000_000],
            'status' => 'success',
            'duration_ms' => 90,
            'step' => 1,
        ]);

        $response = $this->getJson("/api/conversations/{$conversation->id}");

        $response->assertOk()
            ->assertJsonPath('title', 'Transkrip')
            ->assertJsonPath('messages.0.content', 'Berapa penjualan?')
            ->assertJsonPath('agent_runs.0.status', 'success')
            ->assertJsonPath('agent_runs.0.tool_calls.0.tool_name', 'get_sales_summary')
            ->assertJsonPath('agent_runs.0.tool_calls.0.step', 1);
    }

    #[Test]
    public function it_rejects_access_to_another_users_conversation(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $attacker = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($attacker);

        $conversation = Conversation::create(['title' => 'Rahasia', 'user_id' => $owner->id]);

        $this->getJson("/api/conversations/{$conversation->id}")
            ->assertStatus(403);
    }
}
