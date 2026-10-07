<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    public function __construct(
        public readonly string $url,
        public readonly string $model,
        public readonly int $timeout,
    ) {}

    /**
     * Generate a single embedding vector.
     *
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $result = $this->embedMany([$text]);
        return is_array($result) && isset($result[0]) && is_array($result[0]) ? $result[0] : [];
    }

    /**
     * Generate embeddings for multiple texts in one request.
     *
     * @param  array<int, string>  $texts
     * @return array<int, list<float>>
     */
    public function embedMany(array $texts): array
    {
        $payload = [
            'model' => $this->model,
            'input' => $texts,
            'options' => ['num_thread' => 4],
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->url.'/api/embed', $payload);

            $response->throw();

            return $response->json('embeddings', []);
        } catch (ConnectionException) {
            throw new RuntimeException(
                "Tidak dapat terhubung ke Ollama di {$this->url} untuk embedding."
            );
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Ollama mengembalikan error: '.json_encode($exception->response->json() ?? $exception->getMessage())
            );
        }
    }
}
