<?php

namespace App\Console\Commands;

use App\Services\KnowledgeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('knowledge:index {file : Jalur berkas teks (.txt/.md) untuk diindeks} {--source=manual : Nama sumber pengetahuan} {--title= : Judul dokumen (default: nama berkas)} {--chunk-size=800 : Ukuran chunk dalam karakter}')]
#[Description('Indeks berkas teks ke basis pengetahuan (RAG)')]
class KnowledgeIndex extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(KnowledgeService $knowledge): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (! in_array($extension, ['txt', 'md', 'markdown'], true)) {
            $this->error("Ekstensi '{$extension}' belum didukung. Gunakan .txt atau .md.");

            return self::FAILURE;
        }

        $content = (string) file_get_contents($file);
        $title = $this->option('title') ?? pathinfo($file, PATHINFO_FILENAME);

        $this->info("Mengindeks '{$title}' ...");

        $document = $knowledge->indexDocument(
            title: $title,
            content: $content,
            chunkSize: (int) $this->option('chunk-size'),
            sourceName: $this->option('source'),
        );

        $this->info("Selesai: {$document->chunks()->count()} chunk dari dokumen #{$document->id} disimpan.");

        return self::SUCCESS;
    }
}
