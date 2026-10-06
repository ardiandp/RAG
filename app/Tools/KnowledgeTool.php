<?php

namespace App\Tools;

use App\Services\KnowledgeService;
use App\Tools\Contracts\ToolInterface;
use Illuminate\Support\Facades\Validator;

class KnowledgeTool implements ToolInterface
{
    public function __construct(private readonly KnowledgeService $knowledge) {}

    public function name(): string
    {
        return 'search_knowledge';
    }

    public function description(): string
    {
        return 'Mencari informasi dari basis pengetahuan internal (SOP, dokumen, katalog, pengetahuan perusahaan) berdasarkan semantic search. Gunakan untuk pertanyaan yang butuh informasi dari dokumen, bukan dari data transaksi.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Pertanyaan atau kata kunci yang ingin dicari.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Jumlah hasil maksimal (default 3).',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function permission(): ?string
    {
        return 'knowledge.view';
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'query' => ['required', 'string', 'max:500'],
            'limit' => ['nullable', 'integer', 'between:1,10'],
        ])->validate();

        $chunks = $this->knowledge->search(
            $validated['query'],
            (int) ($validated['limit'] ?? config('ollama.top_k', 3)),
        );

        $results = $chunks->map(fn ($chunk) => [
            'source' => $chunk->document?->source?->name,
            'document' => $chunk->document?->title,
            'chunk_index' => $chunk->chunk_index,
            'similarity' => round(1 - (float) $chunk->distance, 4),
            'content' => $chunk->content,
        ])->all();

        return [
            'count' => count($results),
            'results' => $results,
        ];
    }
}
