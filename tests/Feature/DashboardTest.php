<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\ToolCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private const DIMENSIONS = 768;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/dashboard/runs')->assertRedirect('/login');
        $this->get('/dashboard/knowledge')->assertRedirect('/login');
    }

    #[Test]
    public function regular_users_cannot_access_the_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    #[Test]
    public function the_login_page_is_accessible(): void
    {
        $this->get('/login')->assertOk()->assertSee('IRNIS AI');
    }

    #[Test]
    public function an_admin_can_log_in_and_reaches_the_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'secret123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin);
        $this->get('/dashboard')->assertOk()->assertSee($admin->email);
    }

    #[Test]
    public function wrong_credentials_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function logout_ends_the_session(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    #[Test]
    public function dashboard_pages_render_with_activity_data(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'user']);
        $agent = Agent::factory()->create(['name' => 'Asisten Penjualan']);

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'agent_id' => $agent->id,
            'title' => 'Ringkasan penjualan',
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Berapa total penjualan September?',
        ]);

        $run = AgentRun::factory()->create([
            'conversation_id' => $conversation->id,
            'agent_id' => $agent->id,
            'user_id' => $user->id,
            'status' => 'success',
            'input' => 'Berapa total penjualan September?',
            'output' => 'Total penjualan September adalah 100.000.',
            'steps' => 1,
        ]);

        ToolCall::factory()->create([
            'agent_run_id' => $run->id,
            'tool_id' => null,
            'tool_name' => 'get_sales_summary',
            'arguments' => ['month' => '2026-09'],
            'result' => ['total_sales' => 100000],
            'status' => 'success',
            'step' => 1,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')->assertOk()->assertSee($admin->email)
            ->assertSeeInOrder(['Conversations', 'Agent Runs']);

        $this->actingAs($admin)
            ->get('/dashboard/conversations')->assertOk()->assertSee('Ringkasan penjualan');

        $this->actingAs($admin)
            ->get('/dashboard/conversations/'.$conversation->id)->assertOk()
            ->assertSee('Berapa total penjualan September?')
            ->assertSee('get_sales_summary');

        $this->actingAs($admin)
            ->get('/dashboard/runs')->assertOk()->assertSee('Berapa total penjualan September?');

        $this->actingAs($admin)
            ->get('/dashboard/tools')->assertOk()->assertSee('get_sales_summary');

        $this->actingAs($admin)
            ->get('/dashboard/knowledge')->assertOk()->assertSee('Knowledge Base');
    }

    #[Test]
    public function an_admin_can_upload_a_document_from_the_dashboard(): void
    {
        $admin = $this->admin();
        $this->fakeEmbeddings();

        $this->actingAs($admin)->post('/dashboard/knowledge', [
            'file' => UploadedFile::fake()->createWithContent(
                'sop-cuti.txt',
                "SOP cuti: pengajuan paling lambat 3 hari sebelum cuti.\nDisetujui oleh atasan langsung.",
            ),
            'title' => 'SOP Cuti',
            'source' => 'Upload Dashboard',
        ])->assertRedirect()->assertSessionHas('status');

        $document = Document::where('title', 'SOP Cuti')->first();
        $this->assertNotNull($document);
        $this->assertSame('Upload Dashboard', $document->source->name);
        $this->assertSame('indexed', $document->status);
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
