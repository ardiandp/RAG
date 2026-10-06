<?php

namespace Tests\Feature;

use App\Models\KnowledgeSource;
use App\Services\KnowledgeService;
use App\Services\TextChunker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private const DIMENSIONS = 768;

    #[Test]
    public function it_indexes_a_document_into_embedded_chunks(): void
    {
        $this->fakeEmbeddings();

        $content = 'A. Kebijakan retur: pelanggan dapat mengajukan retur dalam 7 hari setelah produk diterima. '.
            'Barang dikembalikan harus dalam keadaan baik dan lengkap dengan kemasan asli. '.
            'B. Proses: pengajuan ditinjau tim layanan pelanggan maksimal 3 hari kerja. '.
            'Keputusan disampaikan melalui email terdaftar pelanggan.';

        $document = app(KnowledgeService::class)->indexDocument('SOP Retur', $content, sourceName: 'SOP Internal');

        $this->assertSame('indexed', $document->status);
        $this->assertSame('SOP Internal', $document->source->name);
        $this->assertSame(TextChunker::chunk($content), $document->chunks->pluck('content')->all());
        $this->assertNotEmpty($document->chunks->first()->embedding);
    }

    #[Test]
    public function it_finds_the_most_relevant_chunk_for_a_query(): void
    {
        $this->fakeEmbeddings();

        $content = 'A. Kebijakan retur: pelanggan dapat mengajukan retur dalam 7 hari setelah produk diterima. '.
            'Barang dikembalikan harus dalam keadaan baik dan lengkap dengan kemasan asli. '.
            'B. Proses: pengajuan ditinjau tim layanan pelanggan maksimal 3 hari kerja. '.
            'Keputusan disampaikan melalui email terdaftar pelanggan.';

        $document = app(KnowledgeService::class)->indexDocument('SOP Retur', $content, sourceName: 'SOP Internal');

        $results = app(KnowledgeService::class)->search('Berapa hari batas pengajuan retur?', 1);

        $this->assertCount(1, $results);
        $this->assertSame($document->id, $results->first()->document_id);
        $this->assertSame(0, $results->first()->chunk_index);
        $this->assertSame(0.0, (float) $results->first()->distance);
    }

    #[Test]
    public function reuse_of_source_name_resuses_the_source(): void
    {
        $this->fakeEmbeddings();

        app(KnowledgeService::class)->indexDocument('Dokumen Pertama', 'Konten pertama dokumen.', sourceName: 'SOP');
        app(KnowledgeService::class)->indexDocument('Dokumen Kedua', 'Konten kedua dokumen.', sourceName: 'SOP');

        $this->assertSame(1, KnowledgeSource::where('name', 'SOP')->count());
        $this->assertSame(2, KnowledgeSource::where('name', 'SOP')->first()->documents()->count());
    }

    /**
     * @return array<int, float>
     */
    private function unitVector(int $ones): array
    {
        $vector = array_fill(0, self::DIMENSIONS, 0.0);
        $vector[$ones] = 1.0;

        return $vector;
    }

    private function fakeEmbeddings(): void
    {
        Http::fake(function ($request) {
            $inputs = $request['input'];

            if (count($inputs) > 1) {
                return Http::response([
                    'embeddings' => [$this->unitVector(0), $this->unitVector(1)],
                ]);
            }

            return Http::response([
                'embeddings' => [$this->unitVector(0)],
            ]);
        });
    }
}
