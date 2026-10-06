<?php

namespace Tests\Feature;

use App\Agents\AgentRunner;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentRunnerTest extends TestCase
{
    use RefreshDatabase;

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

    private function answerResponse(string $content): array
    {
        return [
            'message' => ['role' => 'assistant', 'content' => $content],
            'done' => true,
        ];
    }

    #[Test]
    public function it_executes_a_tool_and_produces_a_final_answer(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push($this->toolCallResponse('get_customer_count'))
                ->push($this->answerResponse('Tercatat ada 3 customer.')),
        ]);

        app('config')->set('agent.max_steps', 5);

        $result = app(AgentRunner::class)->run(['message' => 'Berapa jumlah customer?', 'user_id' => $user->id]);

        $this->assertSame('Tercatat ada 3 customer.', $result['answer']);
        $this->assertSame('success', $result['status']);
        $this->assertSame(2, $result['steps']);

        $conversation = Conversation::query()->sole();
        $this->assertSame(['user', 'assistant'], $conversation->messages->pluck('role')->all());
        $this->assertSame($user->id, $conversation->user_id);

        $run = $conversation->agentRuns()->sole();
        $this->assertSame('success', $run->status);
        $this->assertSame(2, $run->steps);

        $toolCall = $run->toolCalls()->sole();
        $this->assertSame('get_customer_count', $toolCall->tool_name);
        $this->assertSame('success', $toolCall->status);
        $this->assertSame(1, $toolCall->step);
        $this->assertNotNull($toolCall->result);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agent_run.started', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agent_run.finished', 'user_id' => $user->id]);
    }

    #[Test]
    public function it_denies_a_tool_call_when_user_lacks_permission(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push($this->toolCallResponse('get_customer_count'))
                ->push($this->answerResponse('Saya tidak dapat mengakses data tersebut.')),
        ]);

        app('config')->set('agent.max_steps', 5);

        $result = app(AgentRunner::class)->run(['message' => 'Berapa jumlah customer?', 'user_id' => $user->id]);

        $this->assertSame('success', $result['status']);
        $this->assertSame('Saya tidak dapat mengakses data tersebut.', $result['answer']);

        $toolCall = Conversation::query()->sole()->agentRuns()->sole()->toolCalls()->sole();
        $this->assertSame('denied', $toolCall->status);
        $this->assertStringContainsString("Akses ditolak: diperlukan izin 'customer.view'.", $toolCall->error);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tool_call.denied', 'user_id' => $user->id]);
    }

    #[Test]
    public function it_stops_when_max_steps_is_reached(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response($this->toolCallResponse('get_customer_count')),
        ]);

        app('config')->set('agent.max_steps', 2);

        $result = app(AgentRunner::class)->run(['message' => 'Lakukan sesuatu.', 'user_id' => $user->id]);

        $this->assertSame('stopped', $result['status']);
        $this->assertSame(2, $result['steps']);
        $this->assertSame(2, Conversation::query()->sole()->agentRuns()->sole()->toolCalls()->count());
    }

    #[Test]
    public function unknown_tool_is_recorded_as_error_and_run_continues(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push($this->toolCallResponse('tool_tidak_ada'))
                ->push($this->answerResponse('Selesai.')),
        ]);

        app('config')->set('agent.max_steps', 5);

        $result = app(AgentRunner::class)->run(['message' => 'Panggil tool.', 'user_id' => $user->id]);

        $this->assertSame('Selesai.', $result['answer']);
        $this->assertSame('success', $result['status']);

        $toolCall = Conversation::query()->sole()->agentRuns()->sole()->toolCalls()->sole();
        $this->assertSame('error', $toolCall->status);
    }

    #[Test]
    public function it_executes_a_tool_declared_as_text_when_tool_calls_is_empty(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push($this->answerResponse('{"name": "get_customer_count", "arguments": {}}'))
                ->push($this->answerResponse('Tercatat ada 3 customer.')),
        ]);

        app('config')->set('agent.max_steps', 5);

        $result = app(AgentRunner::class)->run(['message' => 'Berapa jumlah customer?', 'user_id' => $user->id]);

        $this->assertSame('Tercatat ada 3 customer.', $result['answer']);
        $this->assertSame('success', $result['status']);

        $toolCall = Conversation::query()->sole()->agentRuns()->sole()->toolCalls()->sole();
        $this->assertSame('get_customer_count', $toolCall->tool_name);
        $this->assertSame('success', $toolCall->status);
    }

    #[Test]
    public function it_marks_the_run_as_failed_when_ollama_is_unreachable(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Failed to connect.');
            },
        ]);

        try {
            app(AgentRunner::class)->run(['message' => 'Halo', 'user_id' => $user->id]);
            $this->fail('Seharusnya melempar RuntimeException.');
        } catch (\RuntimeException) {
            // expected
        }

        $run = Conversation::query()->sole()->agentRuns()->sole();
        $this->assertSame('failed', $run->status);
        $this->assertNotNull($run->error);
        $this->assertNotNull($run->finished_at);
    }
}
