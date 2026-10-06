<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use App\Services\KnowledgeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('knowledge:index {file : Jalur berkas (.txt/.md/.pdf/.docx) untuk diindeks} {--source=manual : Nama sumber pengetahuan} {--title= : Judul dokumen (default: nama berkas)} {--chunk-size=800 : Ukuran chunk dalam karakter}')]
#[Description('Indeks berkas teks/PDF/DOCX ke basis pengetahuan (RAG)')]
class KnowledgeIndex extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(KnowledgeService $knowledge, AuditService $audit): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $title = $this->option('title') ?? pathinfo($file, PATHINFO_FILENAME);

        $this->info("Mengindeks '{$title}' ...");

        try {
            $document = $knowledge->indexFile(
                path: $file,
                chunkSize: (int) $this->option('chunk-size'),
                sourceName: (string) $this->option('source'),
                title: $title,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Selesai: {$document->chunks()->count()} chunk dari dokumen #{$document->id} disimpan.");

        $audit->log('knowledge.document_indexed', context: [
            'document_id' => $document->id,
            'chunks' => $document->chunks()->count(),
            'source' => $document->source->name,
        ]);

        return self::SUCCESS;
    }
}
