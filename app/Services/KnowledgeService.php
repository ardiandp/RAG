<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\KnowledgeSource;
use Illuminate\Support\Collection;

class KnowledgeService
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly DocumentExtractor $extractor,
    ) {}

    /**
     * Ekstrak teks dari berkas (PDF/DOCX/TXT/MD) lalu indeks sebagai
     * dokumen pengetahuan.
     */
    public function indexFile(
        string $path,
        int $chunkSize = 800,
        string $sourceName = 'upload',
        ?string $title = null,
        ?string $extension = null,
    ): Document {
        $title ??= pathinfo($path, PATHINFO_FILENAME);

        return $this->indexDocument(
            title: $title,
            content: $this->extractor->extract($path, $extension),
            chunkSize: $chunkSize,
            sourceName: $sourceName,
        );
    }

    /**
     * Index sebuah dokumen: simpan sumber & dokumen, pecah teks menjadi
     * chunk, buat embedding tiap chunk, lalu simpan ke dokumen chunk.
     */
    public function indexDocument(
        string $title,
        string $content,
        int $chunkSize = 800,
        string $sourceName = 'manual',
    ): Document {
        $source = KnowledgeSource::firstOrCreate(
            ['name' => $sourceName],
            ['type' => 'text'],
        );

        $document = $source->documents()->create([
            'title' => $title,
            'content' => $content,
            'status' => 'indexing',
        ]);

        $chunks = TextChunker::chunk($content, $chunkSize);
        $vectors = $chunks === [] ? [] : $this->embeddings->embedMany($chunks);

        foreach ($chunks as $index => $chunk) {
            $document->chunks()->create([
                'chunk_index' => $index,
                'content' => $chunk,
                'token_count' => count(preg_split('/\s+/u', $chunk) ?: []),
                'embedding' => $this->toVectorLiteral($vectors[$index]),
            ]);
        }

        $document->update(['status' => 'indexed']);

        return $document->refresh();
    }

    /**
     * Cari chunk paling relevan terhadap query menggunakan jarak kosinus
     * pada kolom pgvector.
     *
     * @return Collection<int, DocumentChunk>
     */
    public function search(string $query, int $limit = 3): Collection
    {
        $vector = $this->toVectorLiteral($this->embeddings->embed($query));

        return DocumentChunk::query()
            ->select('document_chunks.*')
            ->selectRaw('embedding <=> ?::vector AS distance', [$vector])
            ->whereNotNull('embedding')
            ->orderBy('distance')
            ->limit($limit)
            ->with(['document' => fn ($query) => $query->with('source')])
            ->get();
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function toVectorLiteral(array $vector): string
    {
        $values = array_map(function (float $value): string {
            $number = rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');

            return $number === '' ? '0' : $number;
        }, $vector);

        return '['.implode(',', $values).']';
    }
}
