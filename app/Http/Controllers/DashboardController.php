<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use RuntimeException;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard.index', [
            'users' => User::count(),
            'conversations' => Conversation::count(),
            'messages' => Message::count(),
            'runs' => AgentRun::count(),
            'runsSuccess' => AgentRun::where('status', 'success')->count(),
            'runsFailed' => AgentRun::where('status', 'failed')->count(),
            'toolCalls' => ToolCall::count(),
            'toolCallsDenied' => ToolCall::where('status', 'denied')->count(),
            'documents' => Document::count(),
            'chunks' => Document::count() > 0 ? DocumentChunk::count() : 0,
            'audits' => AuditLog::count(),
            'latestRuns' => AgentRun::with(['conversation', 'user', 'agent'])
                ->latest('id')->limit(8)->get(),
        ]);
    }

    public function conversations(): View
    {
        return view('dashboard.conversations', [
            'conversations' => Conversation::with(['user', 'agent'])
                ->withCount(['messages', 'agentRuns'])
                ->latest('updated_at')->paginate(15),
        ]);
    }

    public function conversation(Conversation $conversation): View
    {
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

    public function runs(): View
    {
        return view('dashboard.runs', [
            'runs' => AgentRun::with(['conversation', 'user', 'agent', 'toolCalls'])
                ->latest('id')->paginate(15),
        ]);
    }

    public function tools(ToolRegistry $registry): View
    {
        return view('dashboard.tools', [
            'tools' => $registry->all(),
            'usage' => ToolCall::query()
                ->selectRaw('tool_name, status, count(*) as total')
                ->groupBy('tool_name', 'status')
                ->get()
                ->groupBy('tool_name'),
        ]);
    }

    public function knowledge(): View
    {
        return view('dashboard.knowledge', [
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
}
