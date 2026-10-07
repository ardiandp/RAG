<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ToolCall;
use App\Models\User;
use App\Services\KnowledgeService;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    /**
     * Seed data demo: conversations, agent runs, tool calls, dan dokumen
     * pengetahuan (SOP). Knowledge diindeks via Ollama; bila Ollama mati,
     * bagian knowledge dilewati dengan peringatan.
     */
    public function run(KnowledgeService $knowledge): void
    {
        $this->seedAgents();

        if (Conversation::where('title', 'Ringkasan penjualan bulan September')->exists()) {
            $this->command?->info('Data sampel sudah ada, dilewati.');

            return;
        }

        $admin = $this->user('admin@irnis.test', 'Admin IRNIS');
        $test = $this->user('test@example.com', 'Test User');

        $conversations = new ConversationsBuilder($admin, $test);
        $conversations->create();

        $this->command?->info('Data sampel percakapan dibuat ('.Conversation::count().' conversation).');

        $this->seedKnowledge($knowledge);
    }

    private function seedAgents(): void
    {
        $this->agent(
            name: 'Asisten Penjualan',
            description: 'Membantu menjawab ringkasan, tren, dan populasi produk dari data sales.',
            system_prompt: 'Kamu adalah asisten analisis penjualan IRNIS. Jawab ringkas berdasarkan data yang diambil dari tool. Bila angka tidak jelas, tanyakan konteks bulan.',
            tools: ['get_sales_summary', 'get_best_selling_product', 'get_customer_count'],
        );

        $this->agent(
            name: 'Asisten Knowledge',
            description: 'Menjawab pertanyaan seputar SOP dan dokumen internal perusahaan.',
            system_prompt: 'Kamu adalah asisten knowledge base IRNIS. Jawab hanya dari hasil pencarian knowledge; bila tidak ditemukan, katakan tidak tahu dan sarankan langkah selanjutnya.',
            tools: ['search_knowledge'],
        );

        $this->command?->info('Data sampel agent disiapkan ('.Agent::count().' agent).');
    }

    private function agent(string $name, string $description, string $system_prompt, array $tools): void
    {
        Agent::firstOrCreate(
            ['name' => $name],
            [
                'description' => $description,
                'system_prompt' => $system_prompt,
                'tools' => $tools,
                'is_active' => true,
                'max_steps' => 5,
            ],
        );
    }

    private function user(string $email, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password', 'role' => $email === 'admin@irnis.test' ? 'admin' : 'user'],
        );
    }

    private function seedKnowledge(KnowledgeService $knowledge): void
    {
        $documents = [
            'SOP Retur Produk' => 'A. Kebijakan retur: pelanggan dapat mengajukan retur dalam 7 hari setelah produk diterima.
B. Barang yang dikembalikan harus dalam keadaan baik dan lengkap dengan kemasan asli.
C. Proses: pengajuan ditinjau oleh tim layanan pelanggan maksimal 3 hari kerja.
D. Keputusan disampaikan melalui email terdaftar pelanggan.
E. Biaya pengiriman barang retur ditanggung pelanggan, kecuali ada cacat produksi.',
            'SOP Cuti Tahunan' => 'A. Hak cuti tahunan karyawan adalah 12 hari kerja per tahun kalender.
B. Pengajuan cuti dilakukan paling lambat 3 hari kerja sebelum tanggal cuti.
C. Cuti disetujui oleh atasan langsung melalui sistem HRIS.
D. Cuti yang tidak digunakan tidak dapat dibawa ke tahun berikutnya.',
        ];

        try {
            foreach ($documents as $title => $content) {
                $knowledge->indexDocument($title, $content, 800, 'SOP Internal');
            }

            $this->command?->info('Dokumen pengetahuan sampel diindeks.');
        } catch (\Throwable $exception) {
            $this->command?->warn('Gagal mengindeks dokumen sampel (Ollama tidak berjalan?): '.$exception->getMessage());
        }
    }
}

class ConversationsBuilder
{
    public function __construct(
        private readonly User $admin,
        private readonly User $test,
    ) {}

    public function create(): void
    {
        $this->salesConversation();
        $this->returConversation();
        $this->cutiConversation();
    }

    private function salesConversation(): void
    {
        $conversation = $this->conversation($this->test, 'Ringkasan penjualan bulan September');
        $conversation->update(['agent_id' => Agent::where('name', 'Asisten Penjualan')->value('id')]);

        $this->message($conversation, 'user', 'Berapa total penjualan bulan September?');
        $this->message($conversation, 'assistant', 'Total penjualan September adalah Rp 250.000.000 dari 15 pesanan.');

        $run = $this->run($conversation, $this->test, 'success', 1);
        $this->toolCall($run, 'get_sales_summary', 'success', 1, ['month' => '2026-09'], ['month' => '2026-09', 'total_sales' => 250_000_000, 'order_count' => 15]);
    }

    private function returConversation(): void
    {
        $conversation = $this->conversation($this->test, 'Kebijakan retur produk');

        $this->message($conversation, 'user', 'Berapa hari batas pengajuan retur?');
        $this->message($conversation, 'assistant', 'Menurut SOP Internal, pelanggan dapat mengajukan retur dalam 7 hari setelah produk diterima.');

        $run = $this->run($conversation, $this->test, 'success', 2);
        $this->toolCall($run, 'search_knowledge', 'success', 1, ['query' => 'batas hari pengajuan retur'], ['A. Kebijakan retur: pelanggan dapat mengajukan retur dalam 7 hari setelah produk diterima.']);
        $this->toolCall($run, 'search_knowledge', 'success', 2, ['query' => 'proses pengajuan retur'], ['C. Proses: pengajuan ditinjau oleh tim layanan pelanggan maksimal 3 hari kerja.']);

        // Contoh permission ditolak untuk pengguna non-admin.
        $denied = $this->run($conversation, $this->test, 'failed', 1, now()->subMinutes(20));
        $this->toolCall($denied, 'search_knowledge', 'denied', 1, ['query' => 'data internal apa pun'], ['error' => "Akses ditolak: diperlukan izin 'knowledge.view'."]);
    }

    private function cutiConversation(): void
    {
        $conversation = $this->conversation($this->admin, 'SOP cuti karyawan');

        $this->message($conversation, 'user', 'Berapa hari hak cuti tahunan karyawan?');
        $this->message($conversation, 'assistant', 'Hak cuti tahunan karyawan adalah 12 hari kerja per tahun kalender.');

        $run = $this->run($conversation, $this->admin, 'success', 1);
        $this->toolCall($run, 'search_knowledge', 'success', 1, ['query' => 'hak cuti tahunan karyawan'], ['A. Hak cuti tahunan karyawan adalah 12 hari kerja per tahun kalender.']);
    }

    private function conversation(User $user, string $title): Conversation
    {
        return Conversation::create(['user_id' => $user->id, 'title' => $title]);
    }

    private function message(Conversation $conversation, string $role, string $content): void
    {
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
        ]);
    }

    private function run(Conversation $conversation, User $user, string $status, int $steps, ?\DateTimeInterface $at = null): AgentRun
    {
        $at ??= now();

        return AgentRun::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'status' => $status,
            'input' => (string) $conversation->messages()->first()?->content,
            'steps' => $steps,
            'max_steps' => 5,
            'started_at' => $at,
            'finished_at' => $at,
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $result
     */
    private function toolCall(AgentRun $run, string $name, string $status, int $step, array $arguments, array $result): void
    {
        ToolCall::factory()->create([
            'agent_run_id' => $run->id,
            'tool_id' => null,
            'tool_name' => $name,
            'arguments' => $arguments,
            'result' => $result,
            'status' => $status,
            'step' => $step,
            'duration_ms' => 120,
        ]);
    }
}
