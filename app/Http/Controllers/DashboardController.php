<?php

namespace App\Http\Controllers;

use App\Agents\AgentRunner;
use App\Models\AgentRun;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\KnowledgeSource;
use App\Models\Message;
use App\Models\ToolCall;
use App\Models\User;
use App\Services\AuditService;
use App\Services\KnowledgeService;
use App\Tools\ToolRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DashboardController extends Controller
{
    public function chat(Request $request): View
    {
        return $this->chatPage($request, null);
    }

    public function chatThread(Request $request, Conversation $conversation): View
    {
        if (! $request->user()->isAdmin() && $conversation->user_id !== $request->user()->id) {
            abort(403);
        }

        return $this->chatPage($request, $conversation);
    }

    private function chatPage(Request $request, ?Conversation $conversation): View
    {
        $conversation?->load([
            'messages' => fn ($query) => $query->orderBy('id'),
            'agentRuns' => fn ($query) => $query->with('toolCalls')->orderBy('id'),
        ]);

        return view('dashboard.chat', [
            'conversations' => $this->scoped($request)
                ->withCount('messages')->latest('updated_at')->get(),
            'conversation' => $conversation,
        ]);
    }

    public function chatStore(Request $request, AgentRunner $runner): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ]);

        $data['user_id'] = $request->user()->id;

        try {
            $result = $runner->run($data);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'Conversation ini milik pengguna lain.'], 403);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $toolCalls = AgentRun::with('toolCalls')->find($result['run_id'])
            ?->toolCalls->map(fn (ToolCall $call): array => [
                'tool_name' => $call->tool_name,
                'status' => $call->status,
                'arguments' => $call->arguments,
                'result' => $call->result,
            ])->values();

        return response()->json([
            'answer' => $result['answer'],
            'conversation_id' => $result['conversation_id'],
            'status' => $result['status'],
            'steps' => $result['steps'],
            'tool_calls' => $toolCalls,
        ]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $admin = $user->isAdmin();

        return view('dashboard.index', [
            'users' => $admin ? User::count() : 1,
            'conversations' => $this->scoped($request)->count(),
            'messages' => Message::whereIn('conversation_id', $this->scoped($request)->pluck('id'))->count(),
            'runs' => $this->scopedRun($request)->count(),
            'runsSuccess' => $this->scopedRun($request)->where('status', 'success')->count(),
            'runsFailed' => $this->scopedRun($request)->where('status', 'failed')->count(),
            'toolCalls' => $this->scopedToolCall($request)->count(),
            'toolCallsDenied' => $this->scopedToolCall($request)->where('status', 'denied')->count(),
            'documents' => Document::count(),
            'chunks' => Document::count() > 0 ? DocumentChunk::count() : 0,
            'audits' => $admin ? AuditLog::count() : AuditLog::where('user_id', $user->id)->count(),
            'latestRuns' => $this->scopedRun($request)
                ->with(['conversation', 'user', 'agent'])
                ->latest('id')->limit(8)->get(),
        ]);
    }

    public function conversations(Request $request): View
    {
        return view('dashboard.conversations', [
            'conversations' => $this->scoped($request)
                ->with(['user', 'agent'])
                ->withCount(['messages', 'agentRuns'])
                ->latest('updated_at')->paginate(15),
        ]);
    }

    public function conversation(Request $request, Conversation $conversation): View
    {
        if (! $request->user()->isAdmin() && $conversation->user_id !== $request->user()->id) {
            abort(403);
        }

        $conversation->load([
            'user',
            'agent',
            'messages' => fn ($query) => $query->orderBy('id'),
            'agentRuns.toolCalls',
        ]);

        return view('dashboard.conversation', [
            'conversation' => $conversation,
        ]);
    }

    public function runs(Request $request): View
    {
        return view('dashboard.runs.index', [
            'runs' => $this->scopedRun($request)
                ->with(['conversation', 'user', 'agent', 'toolCalls'])
                ->latest('id')->paginate(15),
        ]);
    }

    public function tools(Request $request, ToolRegistry $registry): View
    {
        return view('dashboard.tools.index', [
            'tools' => $registry->all(),
            'usage' => $this->scopedToolCall($request)
                ->selectRaw('tool_name, status, count(*) as total')
                ->groupBy('tool_name', 'status')
                ->get()
                ->groupBy('tool_name'),
        ]);
    }

    public function knowledge(Request $request): View
    {
        return view('dashboard.knowledge', [
            'canUpload' => $request->user()->can('manage_dashboard'),
            'documents' => Document::with('source')->withCount('chunks')
                ->latest('id')->paginate(15),
            'sources' => KnowledgeSource::withCount('documents')->get(),
        ]);
    }

    public function storeKnowledge(Request $request, KnowledgeService $knowledge, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:txt,md,markdown,pdf,docx', 'max:5120'],
            'source' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];

        try {
            $document = $knowledge->indexFile(
                path: $file->getRealPath(),
                sourceName: $data['source'] ?? 'upload',
                title: $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                extension: $file->getClientOriginalExtension(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()])->withInput();
        }

        $audit->log('knowledge.document_uploaded', $request->user(), [
            'document_id' => $document->id,
            'title' => $document->title,
            'chunks' => $document->chunks()->count(),
        ], request: $request);

        return back()->with('status', "Dokumen \"{$document->title}\" berhasil diindeks.");
    }

    public function destroyDocument(Document $document, AuditService $audit): RedirectResponse
    {
        $title = $document->title;
        $document->delete();

        $audit->log('knowledge.document_deleted', null, [
            'document_id' => $document->id,
            'title' => $title,
        ]);

        return back()->with('status', "Dokumen \"{$title}\" dihapus (termasuk chunks-nya).");
    }

    /**
     * Basis data conversation terbatas pada user yang sedang login,
     * kecuali admin yang melihat semuanya.
     */
    private function scoped(Request $request): Builder
    {
        return $request->user()->isAdmin()
            ? Conversation::query()
            : Conversation::query()->where('user_id', $request->user()->id);
    }

    private function scopedRun(Request $request): Builder
    {
        return $request->user()->isAdmin()
            ? AgentRun::query()
            : AgentRun::query()->where('user_id', $request->user()->id);
    }

    private function scopedToolCall(Request $request): Builder
    {
        return ToolCall::query()
            ->when(
                ! $request->user()->isAdmin(),
                fn (Builder $query) => $query->whereHas('agentRun', fn (Builder $run) => $run->where('user_id', $request->user()->id)),
            );
    }
}
