<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\SalesOrder;
use App\Models\User;
use App\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolSystemTest extends TestCase
{
    use RefreshDatabase;

    private const DIMENSIONS = 768;

    #[Test]
    public function registry_registers_tools_with_schemas(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertSame(
            ['get_sales_summary', 'get_best_selling_product', 'get_customer_count', 'search_knowledge'],
            array_keys($registry->all())
        );

        $this->assertNotNull($registry->find('get_sales_summary'));
        $this->assertNull($registry->find('tidak_ada'));

        $firstSchema = $registry->schemas()[0];
        $this->assertSame('function', $firstSchema['type']);
        $this->assertArrayHasKey('name', $firstSchema['function']);
        $this->assertArrayHasKey('parameters', $firstSchema['function']);
    }

    #[Test]
    public function sales_tool_returns_aggregated_sales_for_a_month(): void
    {
        SalesOrder::create([
            'order_number' => 'SO-T-1',
            'customer_name' => 'A',
            'product_name' => 'Laptop',
            'quantity' => 2,
            'unit_price' => 8_000_000,
            'amount' => 16_000_000,
            'order_date' => '2026-09-05',
        ]);
        SalesOrder::create([
            'order_number' => 'SO-T-2',
            'customer_name' => 'B',
            'product_name' => 'Handphone',
            'quantity' => 3,
            'unit_price' => 3_000_000,
            'amount' => 9_000_000,
            'order_date' => '2026-09-10',
        ]);
        SalesOrder::create([
            'order_number' => 'SO-T-3',
            'customer_name' => 'C',
            'product_name' => 'Laptop',
            'quantity' => 1,
            'unit_price' => 8_000_000,
            'amount' => 8_000_000,
            'order_date' => '2026-08-30',
        ]);

        $result = app(ToolRegistry::class)->findOrFail('get_sales_summary')->execute(['month' => '2026-09']);

        $this->assertSame('2026-09', $result['month']);
        $this->assertSame(25_000_000, $result['total_sales']);
        $this->assertSame(2, $result['order_count']);
    }

    #[Test]
    public function product_tool_returns_top_product_filtered_by_month(): void
    {
        SalesOrder::factory()->create(['product_name' => 'Handphone', 'quantity' => 10, 'amount' => 35_000_000, 'order_date' => '2026-09-01']);
        SalesOrder::factory()->create(['product_name' => 'Laptop', 'quantity' => 3, 'amount' => 24_000_000, 'order_date' => '2026-09-02']);
        SalesOrder::factory()->create(['product_name' => 'Laptop', 'quantity' => 99, 'amount' => 50_000_000, 'order_date' => '2026-08-01']);

        $result = app(ToolRegistry::class)->findOrFail('get_best_selling_product')->execute(['month' => '2026-09']);

        $this->assertSame('Handphone', $result['product']);
        $this->assertSame(10, $result['quantity_sold']);
    }

    #[Test]
    public function invalid_tool_arguments_are_rejected(): void
    {
        try {
            app(ToolRegistry::class)->findOrFail('get_sales_summary')->execute(['month' => 'september']);
            $this->fail('Seharusnya melempar ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('month', $exception->errors());
        }
    }

    #[Test]
    public function customer_tool_returns_registered_user_count(): void
    {
        User::factory()->count(3)->create();

        $result = app(ToolRegistry::class)->findOrFail('get_customer_count')->execute([]);

        $this->assertSame(3, $result['customer_count']);
    }

    #[Test]
    public function knowledge_tool_searches_chunks_and_returns_context(): void
    {
        $this->fakeEmbeddings();

        $chunk = DocumentChunk::query()->create([
            'document_id' => Document::factory()->create()->id,
            'chunk_index' => 0,
            'content' => 'Retur produk maksimal 7 hari setelah diterima pelanggan.',
            'token_count' => 10,
            'embedding' => $this->vectorLiteral($this->unitVector(0)),
        ]);

        $result = app(ToolRegistry::class)->findOrFail('search_knowledge')
            ->execute(['query' => 'berapa hari retur', 'limit' => 1]);

        $this->assertSame(1, $result['count']);
        $this->assertSame($chunk->content, $result['results'][0]['content']);
        $this->assertSame(1.0, $result['results'][0]['similarity']);
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

    /**
     * @param  array<int, float>  $vector
     */
    private function vectorLiteral(array $vector): string
    {
        return '['.implode(',', array_map(fn (float $value): string => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.'), $vector)).']';
    }

    private function fakeEmbeddings(): void
    {
        Http::fake([
            '*/api/embed' => Http::response([
                'embeddings' => [$this->unitVector(0)],
            ]),
        ]);
    }
}
