<?php

namespace Tests\Feature;

use App\Agents\AgentRunner;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\User;
use App\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class AgentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    #[Test]
    public function agents_page_is_accessible_to_authenticated_users(): void
    {
        $agent = Agent::factory()->create(['name' => 'Asisten Penjualan', 'max_steps' => 5]);

        $this->actingAs($this->user())
            ->get('/dashboard/agents')
            ->assertOk()->assertSee('Asisten Penjualan');

        $this->actingAs($this->admin())
            ->get('/dashboard/agents')
            ->assertOk()->assertSee('Asisten Penjualan');
    }

    #[Test]
    public function only_admins_can_mutate_agents(): void
    {
        $agent = Agent::factory()->create();

        $this->actingAs($this->user())->get('/dashboard/agents/create')->assertForbidden();
        $this->actingAs($this->user())->get('/dashboard/agents/'.$agent->id.'/edit')->assertForbidden();
        $this->actingAs($this->user())->post('/dashboard/agents', [
            'name' => 'Neo Agent', 'max_steps' => 5,
        ])->assertForbidden();
        $this->actingAs($this->user())->put('/dashboard/agents/'.$agent->id, [
            'name' => 'Ubah', 'max_steps' => 5,
        ])->assertForbidden();
        $this->actingAs($this->user())->delete('/dashboard/agents/'.$agent->id)->assertForbidden();
    }

    #[Test]
    public function an_admin_can_create_an_agent_with_selected_tools(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/dashboard/agents', [
            'name' => 'Asisten Penjualan',
            'description' => 'Ringkasan selling.',
            'system_prompt' => 'Kamu fokus pada data penjualan.',
            'model' => 'qwen2.5:1.5b',
            'max_steps' => 3,
            'is_active' => '1',
            'tools' => ['get_sales_summary', 'search_knowledge'],
        ])->assertRedirect('/dashboard/agents');

        $agent = Agent::where('name', 'Asisten Penjualan')->sole();
        $this->assertSame(3, $agent->max_steps);
        $this->assertTrue($agent->is_active);
        $this->assertSame(['get_sales_summary', 'search_knowledge'], $agent->tools);
        $this->assertSame('qwen2.5:1.5b', $agent->model);

        $this->assertDatabaseHas('audit_logs', ['action' => 'agent.created', 'user_id' => $admin->id]);
    }

    #[Test]
    public function unknown_tool_names_are_rejected(): void
    {
        $this->actingAs($this->admin())->from('/dashboard/agents/create')->post('/dashboard/agents', [
            'name' => 'Hacker',
            'max_steps' => 5,
            'tools' => ['tool_bukan_ada'],
        ])->assertSessionHasErrors('tools.0');

        $this->assertDatabaseMissing('agents', ['name' => 'Hacker']);
    }

    #[Test]
    public function an_admin_can_update_toggle_and_delete_an_agent(): void
    {
        $admin = $this->admin();
        $agent = Agent::factory()->create(['name' => 'Lama', 'is_active' => true, 'max_steps' => 5]);

        $this->actingAs($admin)->put('/dashboard/agents/'.$agent->id, [
            'name' => 'Baru',
            'max_steps' => 7,
            'is_active' => '1',
            'tools' => ['search_knowledge'],
        ])->assertRedirect('/dashboard/agents');

        $agent->refresh();
        $this->assertSame('Baru', $agent->name);
        $this->assertSame(7, $agent->max_steps);
        $this->assertSame(['search_knowledge'], $agent->tools);

        $this->actingAs($admin)->patch('/dashboard/agents/'.$agent->id.'/toggle')->assertRedirect();
        $agent->refresh();
        $this->assertFalse((bool) $agent->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agent.toggled']);

        $this->actingAs($admin)->delete('/dashboard/agents/'.$agent->id)->assertRedirect('/dashboard/agents');
        $this->assertDatabaseMissing('agents', ['name' => 'Baru']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agent.deleted']);
    }

    #[Test]
    public function an_inactive_agent_rejects_chat_for_its_conversations(): void
    {
        $user = $this->user();
        $agent = Agent::factory()->create(['name' => 'Padam', 'is_active' => false]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'agent_id' => $agent->id]);

        $response = $this->actingAs($user)->postJson('/dashboard/chat', [
            'conversation_id' => $conversation->id,
            'message' => 'Halo?',
        ]);

        $response->assertStatus(502)->assertJsonPath('message', "Agent 'Padam' sedang tidak aktif.");
    }

    #[Test]
    public function an_agent_only_bundles_its_selected_tools_for_the_model(): void
    {
        $user = $this->admin();
        $agent = Agent::factory()->create(['name' => 'Pencari SOP', 'tools' => ['search_knowledge']]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'agent_id' => $agent->id]);

        $sentTools = [];
        $calls = 0;

        Http::fake(function ($request) use (&$sentTools, &$calls) {
            if (str_ends_with($request->url(), '/api/embed')) {
                return Http::response(['embeddings' => [array_fill(0, 768, 0.0)]]);
            }

            $sentTools[] = array_map(
                fn (array $tool) => $tool['function']['name'],
                $request->data()['tools'] ?? [],
            );
            $calls++;

            return Http::response($calls === 1
                ? $this->toolCallResponse('search_knowledge', ['query' => 'batas retur'])
                : $this->answerResponse('Batas retur 7 hari.'));
        });

        $result = app(AgentRunner::class)->run([
            'message' => 'Berapa batas retur?',
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
        ]);

        $this->assertSame('success', $result['status']);
        $this->assertSame(['search_knowledge'], $sentTools[0]);

        $conversation->refresh();
        $toolCall = $conversation->agentRuns()->sole()->toolCalls()->sole();
        $this->assertSame('search_knowledge', $toolCall->tool_name);
        $this->assertSame('success', $toolCall->status);
    }

    #[Test]
    public function an_agent_without_selected_tools_can_use_every_tool(): void
    {
        $user = $this->admin();
        $agent = Agent::factory()->create(['tools' => null]);

        $allTools = array_column(app(ToolRegistry::class)->schemas(), 'function.name');

        $this->actingAs($user)->get('/dashboard/settings')->assertOk();

        $this->assertTrue(collect($allTools)->every(
            fn (string $name) => $agent->allowsTool($name)
        ));
    }

    #[Test]
    public function a_conversation_bound_to_an_inactive_agent_fails_through_the_runner(): void
    {
        $user = $this->admin();
        $agent = Agent::factory()->create(['name' => 'Libur', 'is_active' => false]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'agent_id' => $agent->id]);

        try {
            app(AgentRunner::class)->run([
                'message' => 'Halo',
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
            ]);
            $this->fail('Seharusnya melempar RuntimeException.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("Agent 'Libur' sedang tidak aktif.", $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toolCallResponse(string $name, array $arguments = []): array
    {
        return [
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [
                    [
                        'id' => 'call_1',
                        'function' => ['name' => $name, 'arguments' => json_encode($arguments)],
                    ],
                ],
            ],
            'done' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function answerResponse(string $content): array
    {
        return [
            'message' => ['role' => 'assistant', 'content' => $content],
            'done' => true,
        ];
    }
}
