<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeUploadTest extends TestCase
{
    use RefreshDatabase;

    private const DIMENSIONS = 768;

    #[Test]
    public function unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/api/knowledge/documents')->assertUnauthorized();
    }

    #[Test]
    public function it_indexes_an_uploaded_document(): void
    {
        $user = User::factory()->create();
        $this->fakeEmbeddings();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/knowledge/documents', [
            'file' => UploadedFile::fake()->createWithContent(
                'sop-retur.txt',
                "A. Pelanggan dapat mengajukan retur dalam 7 hari.\nB. Barang harus dalam keadaan baik.",
            ),
            'source' => 'Upload Manual',
            'title' => 'SOP Retur',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('title', 'SOP Retur');
        $response->assertJsonPath('status', 'indexed');

        $documentId = $response->json('document_id');
        $document = Document::with('source')->findOrFail($documentId);

        $this->assertSame('Upload Manual', $document->source->name);
        $this->assertGreaterThan(0, $document->chunks()->count());

        $audit = AuditLog::where('action', 'knowledge.document_uploaded')->first();
        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame((int) $documentId, $audit->context['document_id']);
    }

    #[Test]
    public function unsupported_file_types_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/knowledge/documents', [
            'file' => UploadedFile::fake()->createWithContent('data.bin', "\x00\x01binary"),
        ])->assertStatus(422);
    }

    #[Test]
    public function a_corrupt_pdf_returns_a_clear_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/knowledge/documents', [
            'file' => UploadedFile::fake()->createWithContent('rusak.pdf', "%PDF-1.4\n% konten rusak tanpa xref table"),
        ]);

        $response->assertStatus(422);
        $response->assertJsonMissingPath('document_id');
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

            return Http::response([
                'embeddings' => array_map(fn ($index) => $this->unitVector($index), array_keys($inputs)),
            ]);
        });
    }
}
