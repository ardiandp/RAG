<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeDeleteTest extends TestCase
{
    use RefreshDatabase;

    private const DIMENSIONS = 768;

    #[Test]
    public function only_admins_can_delete_documents(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $document = Document::factory()->create(['title' => 'Panduan']);

        $this->actingAs($user)->delete('/dashboard/knowledge/'.$document->id)->assertForbidden();
        $this->actingAs($admin)->delete('/dashboard/knowledge/'.$document->id)->assertRedirect();
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.document_deleted']);
    }

    #[Test]
    public function deleting_a_document_also_removes_its_chunks(): void
    {
        $this->fakeEmbeddings();
        $admin = User::factory()->create(['role' => 'admin']);

        $document = Document::factory()->create(['title' => 'SOP Pengeluaran']);
        DocumentChunk::factory()->create(['document_id' => $document->id]);
        DocumentChunk::factory()->create(['document_id' => $document->id]);

        $this->assertSame(2, $document->chunks()->count());

        $this->actingAs($admin)->delete('/dashboard/knowledge/'.$document->id)->assertRedirect();

        $this->assertSame(0, DocumentChunk::where('document_id', $document->id)->count());
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
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
