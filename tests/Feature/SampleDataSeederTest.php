<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private const DIMENSIONS = 768;

    #[Test]
    public function it_seeds_users_conversations_runs_and_knowledge(): void
    {
        Http::fake(function ($request) {
            $inputs = $request['input'];

            return Http::response([
                'embeddings' => array_map(fn ($index) => $this->unitVector($index), array_keys($inputs)),
            ]);
        });

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@irnis.test', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'role' => 'user']);

        $this->assertTrue(Conversation::where('title', 'Ringkasan penjualan bulan September')->exists());
        $this->assertTrue(Conversation::where('title', 'Kebijakan retur produk')->exists());
        $this->assertTrue(Conversation::where('title', 'SOP cuti karyawan')->exists());

        $this->assertDatabaseHas('tool_calls', ['tool_name' => 'get_sales_summary', 'status' => 'success']);
        $this->assertDatabaseHas('tool_calls', ['tool_name' => 'search_knowledge', 'status' => 'denied']);

        $this->assertTrue(Document::where('title', 'SOP Retur Produk')->exists());
        $this->assertTrue(Document::where('title', 'SOP Cuti Tahunan')->exists());
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        Http::fake(fn () => Http::response(['embeddings' => [$this->unitVector(0)]]));

        $this->seed(DatabaseSeeder::class);
        $count = User::count();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($count, User::count());
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
}
