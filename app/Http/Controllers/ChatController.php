<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\OllamaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ChatController extends Controller
{
    public function handle(Request $request, OllamaService $ollama): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ]);

        $conversation = isset($data['conversation_id'])
            ? Conversation::findOrFail($data['conversation_id'])
            : Conversation::create(['title' => str($data['message'])->limit(60)]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $history = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->filter(fn (Message $message): bool => in_array($message->role, ['user', 'assistant'], true))
            ->map(fn (Message $message): array => [
                'role' => $message->role,
                'content' => (string) $message->content,
            ])
            ->values()
            ->all();

        try {
            $response = $ollama->chat($history);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $answer = (string) data_get($response, 'message.content', '');

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $answer,
        ]);

        return response()->json([
            'answer' => $answer,
        ]);
    }
}
