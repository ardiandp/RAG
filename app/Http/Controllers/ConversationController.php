<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'messages' => fn ($query) => $query->latest()->limit(1),
                'agentRuns' => fn ($query) => $query->latest()->limit(1),
            ])
            ->latest('updated_at')
            ->paginate(20);

        return response()->json($conversations);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Conversation ini milik pengguna lain.'], 403);
        }

        $conversation->load([
            'messages' => fn ($query) => $query->orderBy('id'),
            'agentRuns' => fn ($query) => $query->orderBy('id')->with('toolCalls'),
        ]);

        return response()->json($conversation);
    }
}
