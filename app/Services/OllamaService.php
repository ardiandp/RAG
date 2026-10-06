<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OllamaService
{
    public function __construct(
        public readonly string $url,
        public readonly string $model,
        public readonly int $timeout,
        public readonly float $temperature,
    ) {}

    /**
     * Send a single user prompt and return the plain text answer.
     */
    public function ask(string $prompt, ?string $model = null): string
    {
        $response = $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $model);

        return (string) data_get($response, 'message.content', '');
    }

    /**
     * Send a full conversation and return the raw decoded response.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        $payload = [
            'model' => $model ?? $this->model,
            'messages' => $messages,
            'stream' => false,
            'options' => array_merge(['temperature' => $this->temperature], $options),
        ];

        try {
            $started = hrtime(true);

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->url.'/api/chat', $payload);

            $response->throw();

            $data = $response->json();
            $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);

            Log::info('Ollama chat completed', [
                'model' => $payload['model'],
                'duration_ms' => $durationMs,
                'eval_count' => data_get($data, 'eval_count'),
                'total_duration_ms' => (int) round((int) data_get($data, 'total_duration', 0) / 1_000_000),
            ]);

            return $data;
        } catch (ConnectionException) {
            throw new RuntimeException(
                "Tidak dapat terhubung ke Ollama di {$this->url}. Pastikan Ollama sedang berjalan."
            );
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Ollama mengembalikan error: '.json_encode($exception->response->json() ?? $exception->getMessage())
            );
        } catch (RuntimeException $exception) {
            Log::error('Ollama chat failed', [
                'model' => $payload['model'],
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
