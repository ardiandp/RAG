<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiveChatE2ETest extends TestCase
{
    use RefreshDatabase;

    /**
     * Live end-to-end test: routes through the HTTP kernel against the
     * real PostgreSQL database and the real local Ollama (Qwen).
     *
     * Skipped unless OLLAMA_LIVE_TEST=true is set, so the normal test
     * suite stays deterministic.
     */
    #[Test]
    public function chat_endpoint_answers_against_real_ollama(): void
    {
        if (getenv('OLLAMA_LIVE_TEST') !== 'true') {
            $this->markTestSkipped('Set OLLAMA_LIVE_TEST=true untuk menjalankan E2E live.');
        }

        $response = $this->postJson('/api/chat', [
            'message' => 'Jawab dalam satu kalimat: Apa itu PostgreSQL?',
        ]);

        $response->assertOk();
        $answer = $response->json('answer');

        $this->assertIsString($answer);
        $this->assertNotEmpty($answer);
        $this->assertStringContainsStringIgnoringCase('postgres', $answer);

        $conversation = Conversation::with('messages')->sole();
        $this->assertSame(['user', 'assistant'], $conversation->messages->pluck('role')->all());
    }

    /**
     * Live tool-calling flow: the agent must call a real tool, get real
     * data from PostgreSQL, and answer based on it.
     */
    #[Test]
    public function agent_calls_a_tool_and_answers_from_real_data(): void
    {
        if (getenv('OLLAMA_LIVE_TEST') !== 'true') {
            $this->markTestSkipped('Set OLLAMA_LIVE_TEST=true untuk menjalankan E2E live.');
        }

        SalesOrder::create([
            'order_number' => 'SO-LIVE-1',
            'customer_name' => 'Budi',
            'product_name' => 'Laptop',
            'quantity' => 2,
            'unit_price' => 8_000_000,
            'amount' => 16_000_000,
            'order_date' => '2026-09-05',
        ]);
        SalesOrder::create([
            'order_number' => 'SO-LIVE-2',
            'customer_name' => 'Siti',
            'product_name' => 'Handphone',
            'quantity' => 3,
            'unit_price' => 3_000_000,
            'amount' => 9_000_000,
            'order_date' => '2026-09-12',
        ]);

        $response = $this->postJson('/api/chat', [
            'message' => 'Gunakan tool get_sales_summary untuk menghitung total penjualan bulan 2026-09. Jawab dengan angka saja.',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('answer'));

        $run = Conversation::with('agentRuns.toolCalls')->sole()->agentRuns->sole();
        $this->assertSame('success', $run->status);
        $this->assertTrue($run->toolCalls->isNotEmpty(), 'Agent seharusnya memanggil tool.');
        $this->assertSame('get_sales_summary', $run->toolCalls->first()->tool_name);
        $this->assertSame('success', $run->toolCalls->first()->status);
    }
}
