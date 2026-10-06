<?php

namespace Tests\Feature;

use App\Services\DocumentExtractor;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class DocumentExtractorTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_extracts_plain_text_files(): void
    {
        $file = $this->writeFixture('catatan.txt', "Retur maksimal 7 hari.\nDengan kemasan asli.");

        $this->assertSame("Retur maksimal 7 hari.\nDengan kemasan asli.", $this->extractor()->extract($file));
    }

    #[Test]
    public function it_extracts_text_from_a_pdf(): void
    {
        $file = $this->writeFixture('dokumen.pdf', $this->makePdf('Kebijakan retur 7 hari'));

        $this->assertStringContainsString('Kebijakan retur 7 hari', $this->extractor()->extract($file));
    }

    #[Test]
    public function it_extracts_text_from_a_docx(): void
    {
        $file = $this->writeFixture('dokumen.docx', $this->makeDocx([
            'Kebijakan retur.',
            'Pengajuan ditinjau maksimal 3 hari kerja.',
        ]));

        $text = $this->extractor()->extract($file);

        $this->assertStringContainsString('Kebijakan retur.', $text);
        $this->assertStringContainsString('Pengajuan ditinjau maksimal 3 hari kerja.', $text);
        $this->assertStringNotContainsString('<w:', $text);
    }

    #[Test]
    public function it_rejects_unsupported_extensions(): void
    {
        $file = $this->writeFixture('data.xls', 'binary-ish');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum didukung');

        $this->extractor()->extract($file);
    }

    private function extractor(): DocumentExtractor
    {
        return app(DocumentExtractor::class);
    }

    private function writeFixture(string $name, string $content): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('rag-fixture-', true).'-'.$name;
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /** @param  list<string>  $paragraphs */
    private function makeDocx(array $paragraphs): string
    {
        $body = '';
        foreach ($paragraphs as $paragraph) {
            $body .= "<w:p><w:r><w:t xml:space=\"preserve\">{$paragraph}</w:t></w:r></w:p>";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'.
            '<w:body>'.$body.'</w:body></w:document>';

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('docx-', true).'.docx';
        $archive = new ZipArchive;
        $archive->open($tmp, ZipArchive::CREATE);
        $archive->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
        $archive->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships/>');
        $archive->addFromString('word/document.xml', $xml);
        $archive->close();

        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function makePdf(string $text): string
    {
        // Minimal PDF satu halaman dengan satu teks; offset xref dihitung agar valid.
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '.
                '/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        ];

        $stream = 'BT /F1 12 Tf 72 720 Td ('.$text.') Tj ET';
        $objects[] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n".
            "startxref\n".$xref."\n%%EOF\n";

        return $pdf;
    }
}
