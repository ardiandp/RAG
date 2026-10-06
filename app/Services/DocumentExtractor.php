<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class DocumentExtractor
{
    public function __construct(private readonly PdfParser $pdfParser) {}

    /**
     * Ekstrak teks dari berkas berdasarkan ekstensi.
     */
    public function extract(string $path, ?string $extension = null): string
    {
        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'txt', 'md', 'markdown' => $this->plainText($path),
            'pdf' => $this->pdf($path),
            'docx' => $this->docx($path),
            default => throw new RuntimeException("Ekstensi '{$extension}' belum didukung."),
        };
    }

    private function plainText(string $path): string
    {
        return (string) file_get_contents($path);
    }

    private function pdf(string $path): string
    {
        try {
            return (string) $this->pdfParser->parseFile($path)->getText();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Gagal membaca file PDF: '.$exception->getMessage());
        }
    }

    private function docx(string $path): string
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException('Gagal membuka file DOCX.');
        }

        $xml = $archive->getFromName('word/document.xml');
        $archive->close();

        if ($xml === false) {
            throw new RuntimeException('DOCX tidak valid: word/document.xml tidak ditemukan.');
        }

        $xml = str_ireplace(['</w:p>', '</w:tr>', '<w:tab/>'], ["\n", "\n", ' '], $xml);
        $text = strip_tags((string) $xml);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\n{3,}/', "\n\n", (string) $text));
    }
}
