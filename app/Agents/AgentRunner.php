<?php

namespace App\Agents;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OllamaService;
use App\Tools\ToolRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AgentRunner
{
    private const DEFAULT_SYSTEM_PROMPT = 'Kamu adalah asisten AI IRNIS. Untuk pertanyaan umum jawab langsung. Jika pertanyaan membutuhkan data atau informasi dari dokumen, panggil tool yang tersedia lalu jawab berdasarkan hasil tool. Saat memanggil tool, gunakan format tool_calls yang disediakan sistem, JANGAN menulis pemanggilan tool sebagai teks jawaban biasa.';

    public function __construct(
        private readonly OllamaService $ollama,
        private readonly ToolRegistry $registry,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array{message: string, conversation_id?: int, agent_id?: int, user_id?: int, file_name?: string, file_excerpt?: string}  $input
     * @return array{answer: string, run_id: int, conversation_id: int, status: string, steps: int}
     */
    public function run(array $input): array
    {
        $message = $input['message'];
        $userId = isset($input['user_id']) ? (int) $input['user_id'] : null;

        $conversation = isset($input['conversation_id'])
            ? Conversation::findOrFail($input['conversation_id'])
            : Conversation::create(['title' => str($message)->limit(60), 'user_id' => $userId]);

        $this->assertOwnership($conversation, $userId);

        $user = $conversation->user;

        $agent = isset($input['agent_id'])
            ? Agent::find($input['agent_id'])
            : $conversation->agent;

        if ($agent !== null && ! $agent->is_active) {
            throw new RuntimeException("Agent '{$agent->name}' sedang tidak aktif.");
        }

        $maxSteps = $agent?->max_steps ?? (int) config('agent.max_steps', 5);

        $run = AgentRun::create([
            'conversation_id' => $conversation->id,
            'agent_id' => $agent?->id,
            'user_id' => $conversation->user_id,
            'status' => 'running',
            'input' => $message,
            'steps' => 0,
            'max_steps' => $maxSteps,
            'started_at' => now(),
        ]);

        $this->audit->log('agent_run.started', $user, [
            'conversation_id' => $conversation->id,
            'run_id' => $run->id,
        ]);

        $userMessage = [
            'role' => 'user',
            'content' => $message,
        ];

        $metadata = [];

        if (! empty($input['file_excerpt'])) {
            $metadata = array_filter([
                'file' => $input['file_name'] ?? null,
                'file_excerpt' => $input['file_excerpt'],
            ]);
        }

        $conversation->messages()->create($userMessage + ['metadata' => $metadata === [] ? null : $metadata]);

        $history = $this->buildHistory($conversation, $agent);
        $tools = collect($this->registry->schemas())
            ->filter(fn (array $schema): bool => $agent === null || $agent->allowsTool((string) $schema['function']['name']))
            ->values()
            ->all();

        $steps = 0;
        $finalContent = '';

        while ($steps < $maxSteps) {
            $steps++;
            $run->update(['steps' => $steps]);

            try {
                $response = $this->ollama->chat($history, model: $agent?->model, tools: $tools);
            } catch (RuntimeException $exception) {
                $run->update([
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                    'finished_at' => now(),
                ]);

                throw $exception;
            }

            $content = (string) data_get($response, 'message.content', '');
            $toolCalls = data_get($response, 'message.tool_calls', []);

            if (empty($toolCalls)) {
                [$toolCalls, $content] = $this->extractTextualToolCalls($content);
            }

            if (empty($toolCalls)) {
                $finalContent = $content;
                break;
            }

            $history[] = $this->toAssistantMessage($content, $toolCalls);

            foreach ($toolCalls as $call) {
                $name = (string) data_get($call, 'function.name', '');
                $arguments = $this->decodeArguments(data_get($call, 'function.arguments', '{}'));

                [$result, $status, $error, $durationMs] = $this->executeTool($name, $arguments, $steps, $user);

                $run->toolCalls()->create([
                    'tool_name' => $name,
                    'arguments' => $arguments,
                    'result' => $result,
                    'status' => $status,
                    'error' => $error,
                    'duration_ms' => $durationMs,
                    'step' => $steps,
                ]);

                if ($status === 'denied') {
                    $this->audit->log('tool_call.denied', $user, [
                        'conversation_id' => $conversation->id,
                        'run_id' => $run->id,
                        'tool_name' => $name,
                        'step' => $steps,
                    ]);
                }

                $history[] = [
                    'role' => 'tool',
                    'content' => json_encode($result),
                    'name' => $name,
                ];
            }
        }

        $status = $finalContent !== '' ? 'success' : 'stopped';

        if ($finalContent === '') {
            $finalContent = 'Saya tidak dapat menyelesaikan jawaban dalam batas langkah yang tersedia ('.$maxSteps.').';
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $finalContent,
        ]);

        $run->update([
            'status' => $status,
            'output' => $finalContent,
            'finished_at' => now(),
        ]);

        $this->audit->log('agent_run.finished', $user, [
            'conversation_id' => $conversation->id,
            'run_id' => $run->id,
            'status' => $status,
            'steps' => $steps,
        ]);

        return [
            'answer' => $finalContent,
            'run_id' => $run->id,
            'conversation_id' => $conversation->id,
            'status' => $status,
            'steps' => $steps,
        ];
    }

    private function assertOwnership(Conversation $conversation, ?int $userId): void
    {
        if ($userId !== null && $conversation->user_id !== null && (int) $conversation->user_id !== $userId) {
            throw new AuthorizationException('Conversation ini milik pengguna lain.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildHistory(Conversation $conversation, ?Agent $agent): array
    {
        $history = [
            [
                'role' => 'system',
                'content' => $agent?->system_prompt ?? self::DEFAULT_SYSTEM_PROMPT,
            ],
        ];

        $conversation->messages()
            ->orderBy('id')
            ->get()
            ->each(function ($message) use (&$history): void {
                if (! in_array($message->role, ['system', 'user', 'assistant'], true)) {
                    return;
                }

                $content = (string) $message->content;

                $excerpt = data_get($message->metadata, 'file_excerpt');

                if (is_string($excerpt) && $excerpt !== '') {
                    $fileName = data_get($message->metadata, 'file', 'lampiran');
                    $content = "[Berkas terlampir: {$fileName}]\n{$excerpt}\n\n".$content;
                }

                $history[] = [
                    'role' => $message->role,
                    'content' => $content,
                ];
            });

        return $history;
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @return array{role: string, content: string|null, tool_calls: array<int, array<string, mixed>>}
     */
    private function toAssistantMessage(string $content, array $toolCalls): array
    {
        $calls = [];

        foreach ($toolCalls as $call) {
            $name = (string) data_get($call, 'function.name', '');
            $arguments = (object) $this->decodeArguments(data_get($call, 'function.arguments', '{}'));

            $calls[] = [
                'function' => [
                    'name' => $name,
                    'arguments' => $arguments,
                ],
            ];
        }

        return [
            'role' => 'assistant',
            'content' => $content === '' ? null : $content,
            'tool_calls' => $calls,
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: string|null, 3: int}
     */
    private function executeTool(string $name, array $arguments, int $step, ?User $user): array
    {
        $started = hrtime(true);

        try {
            $tool = $this->registry->findOrFail($name);

            $permission = $tool->permission();

            if ($permission !== null && (($user === null) || Gate::forUser($user)->denies($permission))) {
                $error = "Akses ditolak: diperlukan izin '{$permission}'.";

                return [['error' => $error], 'denied', $error, 0];
            }

            $result = $tool->execute($arguments);
            $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);

            return [$result, 'success', null, $durationMs];
        } catch (ValidationException $exception) {
            $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
            $error = implode('; ', array_map(fn ($messages): string => implode(', ', $messages), $exception->errors()));
            $result = ['error' => $error];

            return [$result, 'error', $error, $durationMs];
        } catch (RuntimeException $exception) {
            $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);

            return [['error' => $exception->getMessage()], 'error', $exception->getMessage(), $durationMs];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeArguments(mixed $arguments): array
    {
        if (is_array($arguments)) {
            return $arguments;
        }

        $decoded = json_decode((string) $arguments, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Model kecil kadang menulis pemanggilan tool sebagai teks JSON di
     * kolom content alih-alih field tool_calls. Deteksi pola tersebut
     * dan jadikan tool call yang dapat dieksekusi.
     *
     * @return array{
     *     0: array<int, array<string, mixed>>,
     *     1: string
     * }
     */
    private function extractTextualToolCalls(string $content): array
    {
        $calls = [];
        $candidates = [];

        if (preg_match_all('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $candidates = $matches[1];
        }

        if (json_decode($content, true) !== null) {
            $candidates[] = trim($content);
        }

        foreach ($candidates as $json) {
            $decoded = json_decode($json, true);

            if (! is_array($decoded) || empty($decoded['name']) || ! array_key_exists('arguments', $decoded)) {
                continue;
            }

            $calls[] = [
                'function' => [
                    'name' => (string) $decoded['name'],
                    'arguments' => $decoded['arguments'],
                ],
            ];

            $content = trim(str_replace($json, '', $content));
            $content = trim((string) preg_replace('/\s+/u', ' ', $content));
        }

        return [$calls, $content];
    }
}
