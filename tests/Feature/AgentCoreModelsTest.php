<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tool;
use App\Models\ToolCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentCoreModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_tables_are_connectable_and_readable(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create();
        $tool = Tool::factory()->create(['name' => 'get_sales_summary']);

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'agent_id' => $agent->id,
        ]);

        $conversation->messages()->saveMany([
            new Message(['role' => 'user', 'content' => 'Halo']),
            new Message(['role' => 'assistant', 'content' => 'Halo, ada yang bisa dibantu?']),
        ]);

        $run = AgentRun::factory()->create([
            'conversation_id' => $conversation->id,
            'agent_id' => $agent->id,
            'user_id' => $user->id,
            'input' => 'Halo',
            'output' => 'Halo, ada yang bisa dibantu?',
            'status' => 'success',
            'steps' => 1,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $run->toolCalls()->save(new ToolCall([
            'tool_id' => $tool->id,
            'tool_name' => $tool->name,
            'arguments' => ['q' => 'x'],
            'result' => ['ok' => true],
            'status' => 'success',
            'duration_ms' => 10,
            'step' => 1,
        ]));

        $loaded = Conversation::with(['user', 'agent', 'messages', 'agentRuns.toolCalls'])->findOrFail($conversation->id);

        $this->assertSame($user->name, $loaded->user->name);
        $this->assertSame($agent->fresh()->name, $loaded->agent->name);
        $this->assertCount(2, $loaded->messages);
        $this->assertSame(['user', 'assistant'], $loaded->messages->pluck('role')->all());

        $loadedRun = $loaded->agentRuns->first();
        $this->assertNotNull($loadedRun);
        $this->assertSame('success', $loadedRun->status);
        $this->assertCount(1, $loadedRun->toolCalls);
        $this->assertSame('get_sales_summary', $loadedRun->toolCalls->first()->tool_name);
        $this->assertSame(['ok' => true], $loadedRun->toolCalls->first()->result);
    }

    public function test_migrations_can_be_reset_and_reapplied(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();

        $this->assertTrue(
            app('db')->getSchemaBuilder()->hasTable('agent_runs')
        );
    }
}
