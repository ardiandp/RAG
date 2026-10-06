<?php

namespace Tests\Feature;

use App\Agents\AgentRunner;
use App\Models\Conversation;
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
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push($this->toolCallResponse('get_customer_count'))
                ->push($this->answerResponse('Tercatat ada 3 customer.')),
        ]);

        app('config')->set('agent.max_steps', 5);

        $result = app(AgentRunner::class)->run(['message' => 'Berapa jumlah customer?']);

        $this->assertSame('Tercatat ada 3 customer.', $result['answer']);
        $this->assertSame('success', $result['status']);
        $this->assertSame(2, $result['steps']);

        $conversation = Conversation::query()->sole();
        $this->assertSame(['user', 'assistant'], $conversation->messages->pluck('role')->all());

        $run = $conversation->agentRuns()->sole();
        $this->assertSame('success', $run->status);
        $this->assertSame(2, $run->steps);

        $toolCall = $run->toolCalls()->sole();
        $this->assertSame('get_customer_count', $toolCall->tool_name);
        $this->assertSame('success', $toolCall->status);
        $this->assertSame(1, $toolCall->step);
        $this->assertNotNull($toolCall->result);
    }

    #[Test]
    public function it_stops_when_max_steps_is_reached(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response($this->toolCallResponse('get_customer_count')),
        ]);

        app('config')->set('agent.max_steps', 2);

        $result = app(AgentRunner::class)->run(['message' => 'Lakukan sesuatu.']);

        $this->assertSame('stopped', $result['status']);
        $this->assertSame(2, $result['steps']);
        $this->assertSame(2, Conversation::query()->sole()->agentRuns()->sole()->toolCalls()->count());
    }

    #[Test]
    public function unknown_tool_is_recorded_as_error_and_run_continues(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push($this->toolCallResponse('tool_tidak_ada'))
                ->push($this->answerResponse('Selesai.')),
        ]);

        app('config')->set('agent.max_steps', 5);

        $result = app(AgentRunner::class)->run(['message' => 'Panggil tool.']);

        $this->assertSame('Selesai.', $result['answer']);
        $this->assertSame('success', $result['status']);

        $toolCall = Conversation::query()->sole()->agentRuns()->sole()->toolCalls()->sole();
        $this->assertSame('error', $toolCall->status);
    }

    #[Test]
    public function it_marks_the_run_as_failed_when_ollama_is_unreachable(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Failed to connect.');
            },
        ]);

        try {
            app(AgentRunner::class)->run(['message' => 'Halo']);
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
