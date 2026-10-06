<?php

namespace App\Http\Controllers;

use App\Agents\AgentRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ChatController extends Controller
{
    public function handle(Request $request, AgentRunner $runner): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
        ]);

        try {
            $result = $runner->run($data);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json([
            'answer' => $result['answer'],
        ]);
    }
}
