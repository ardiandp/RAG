<?php

namespace Tests\Feature;

use App\Services\OllamaService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OllamaServiceTest extends TestCase
{
    private function service(bool $unreachableHost = false): OllamaService
    {
        return new OllamaService(
            url: $unreachableHost ? 'http://127.0.0.1:1' : 'http://127.0.0.1:11434',
            model: 'qwen2.5:1.5b',
            timeout: 1,
            temperature: 0.2,
        );
    }

    #[Test]
    public function it_builds_a_chat_request_and_returns_the_raw_response(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'model' => 'qwen2.5:1.5b',
                'message' => ['role' => 'assistant', 'content' => 'PostgreSQL adalah database relasional.'],
                'done' => true,
                'eval_count' => 5,
                'total_duration' => 1_000_000,
            ]),
        ]);

        $response = $this->service()->chat([
            ['role' => 'user', 'content' => 'Apa itu PostgreSQL?'],
        ]);

        $this->assertSame('PostgreSQL adalah database relasional.', $response['message']['content']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'http://127.0.0.1:11434/api/chat'
                && $payload['model'] === 'qwen2.5:1.5b'
                && $payload['stream'] === false
                && $payload['messages'][0]['role'] === 'user'
                && $payload['messages'][0]['content'] === 'Apa itu PostgreSQL?'
                && $payload['options']['temperature'] === 0.2;
        });
    }

    #[Test]
    public function ask_returns_the_plain_text_answer(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => 'Halo dari Qwen.'],
                'done' => true,
            ]),
        ]);

        $answer = $this->service()->ask('Sapa saya');

        $this->assertSame('Halo dari Qwen.', $answer);
    }

    #[Test]
    public function it_throws_a_clear_runtime_error_when_ollama_is_unreachable(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Failed to connect.');
            },
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak dapat terhubung ke Ollama');

        $this->service()->ask('Halo');
    }
}
