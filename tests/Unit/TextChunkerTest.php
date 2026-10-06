<?php

namespace Tests\Unit;

use App\Services\TextChunker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TextChunkerTest extends TestCase
{
    #[Test]
    public function empty_text_returns_no_chunks(): void
    {
        $this->assertSame([], TextChunker::chunk(''));
        $this->assertSame([], TextChunker::chunk('   '));
    }

    #[Test]
    public function short_text_is_single_chunk(): void
    {
        $this->assertSame(['Halo dunia'], TextChunker::chunk('Halo dunia', 800));
    }

    #[Test]
    public function long_text_is_split_into_overlapping_chunks(): void
    {
        $text = implode(' ', array_fill(0, 500, 'kata'));

        $chunks = TextChunker::chunk($text, 80, 20);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertStringStartsWith('kata', $chunks[0]);
        $this->assertStringEndsWith('kata', $chunks[array_key_last($chunks)]);
        $this->assertGreaterThan(500, str_word_count(implode(' ', $chunks)));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(100, mb_strlen($chunk));
            $this->assertNotEmpty(trim($chunk));
        }
    }

    #[Test]
    public function chunks_break_on_word_boundaries(): void
    {
        $text = str_repeat('perkembangan ', 30);

        $chunks = TextChunker::chunk($text, 30, 5);

        foreach ($chunks as $chunk) {
            $this->assertStringEndsNotWith(' ', $chunk);
            $this->assertStringStartsNotWith(' ', $chunk);
        }
    }
}
