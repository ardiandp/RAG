<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\KnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class KnowledgeUploadController extends Controller
{
    public function store(Request $request, KnowledgeService $knowledge, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:txt,md,markdown,pdf,docx', 'max:5120'],
            'source' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];
        $title = $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        try {
            $document = $knowledge->indexFile(
                path: $file->getRealPath(),
                sourceName: $data['source'] ?? 'upload',
                title: $title,
                extension: $file->getClientOriginalExtension(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $audit->log('knowledge.document_uploaded', $request->user(), [
            'document_id' => $document->id,
            'title' => $document->title,
            'chunks' => $document->chunks()->count(),
        ], request: $request);

        return response()->json([
            'document_id' => $document->id,
            'title' => $document->title,
            'status' => $document->status,
            'chunks' => $document->chunks()->count(),
        ], 201);
    }
}
