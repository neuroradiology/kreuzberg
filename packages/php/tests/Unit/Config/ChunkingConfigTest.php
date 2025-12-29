<?php

declare(strict_types=1);

namespace Kreuzberg\Tests\Unit\Config;

use Kreuzberg\Config\ChunkingConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ChunkingConfig readonly class.
 *
 * Tests construction, serialization, factory methods, readonly enforcement,
 * and handling of integer and boolean properties. Validates that chunking
 * configuration can be properly created, serialized, and maintained in a readonly state.
 *
 * Test Coverage:
 * - Construction with default values
 * - Construction with custom values
 * - toArray() serialization
 * - fromArray() factory method
 * - fromJson() factory method
 * - toJson() serialization
 * - Readonly enforcement (modification prevention)
 * - Type coercion
 * - Invalid JSON handling
 * - Round-trip serialization
 */
#[CoversClass(ChunkingConfig::class)]
#[Group('unit')]
#[Group('config')]
final class ChunkingConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_default_values(): void
    {
        $config = new ChunkingConfig();

        $this->assertSame(512, $config->maxChunkSize);
        $this->assertSame(50, $config->chunkOverlap);
        $this->assertTrue($config->respectSentences);
        $this->assertTrue($config->respectParagraphs);
    }

    #[Test]
    public function it_creates_with_custom_values(): void
    {
        $config = new ChunkingConfig(
            maxChunkSize: 1024,
            chunkOverlap: 100,
            respectSentences: false,
            respectParagraphs: false,
        );

        $this->assertSame(1024, $config->maxChunkSize);
        $this->assertSame(100, $config->chunkOverlap);
        $this->assertFalse($config->respectSentences);
        $this->assertFalse($config->respectParagraphs);
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $config = new ChunkingConfig(
            maxChunkSize: 256,
            chunkOverlap: 25,
        );
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertSame(256, $array['max_chunk_size']);
        $this->assertSame(25, $array['chunk_overlap']);
        $this->assertTrue($array['respect_sentences']);
        $this->assertTrue($array['respect_paragraphs']);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $config = ChunkingConfig::fromArray([]);

        $this->assertSame(512, $config->maxChunkSize);
        $this->assertSame(50, $config->chunkOverlap);
        $this->assertTrue($config->respectSentences);
        $this->assertTrue($config->respectParagraphs);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'max_chunk_size' => 2048,
            'chunk_overlap' => 200,
            'respect_sentences' => false,
            'respect_paragraphs' => true,
        ];
        $config = ChunkingConfig::fromArray($data);

        $this->assertSame(2048, $config->maxChunkSize);
        $this->assertSame(200, $config->chunkOverlap);
        $this->assertFalse($config->respectSentences);
        $this->assertTrue($config->respectParagraphs);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $config = new ChunkingConfig(
            maxChunkSize: 768,
            chunkOverlap: 75,
            respectSentences: true,
            respectParagraphs: false,
        );
        $json = $config->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertSame(768, $decoded['max_chunk_size']);
        $this->assertSame(75, $decoded['chunk_overlap']);
        $this->assertTrue($decoded['respect_sentences']);
        $this->assertFalse($decoded['respect_paragraphs']);
    }

    #[Test]
    public function it_creates_from_json(): void
    {
        $json = json_encode([
            'max_chunk_size' => 512,
            'chunk_overlap' => 50,
            'respect_sentences' => true,
            'respect_paragraphs' => true,
        ]);
        $config = ChunkingConfig::fromJson($json);

        $this->assertSame(512, $config->maxChunkSize);
        $this->assertSame(50, $config->chunkOverlap);
        $this->assertTrue($config->respectSentences);
        $this->assertTrue($config->respectParagraphs);
    }

    #[Test]
    public function it_round_trips_through_json(): void
    {
        $original = new ChunkingConfig(
            maxChunkSize: 1024,
            chunkOverlap: 128,
            respectSentences: false,
            respectParagraphs: true,
        );

        $json = $original->toJson();
        $restored = ChunkingConfig::fromJson($json);

        $this->assertSame($original->maxChunkSize, $restored->maxChunkSize);
        $this->assertSame($original->chunkOverlap, $restored->chunkOverlap);
        $this->assertSame($original->respectSentences, $restored->respectSentences);
        $this->assertSame($original->respectParagraphs, $restored->respectParagraphs);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        ChunkingConfig::fromJson('{ broken }');
    }

    #[Test]
    public function it_enforces_readonly_on_max_chunk_size_property(): void
    {
        $this->expectException(\Error::class);

        $config = new ChunkingConfig(maxChunkSize: 512);
        $config->maxChunkSize = 1024;
    }

    #[Test]
    public function it_enforces_readonly_on_chunk_overlap_property(): void
    {
        $this->expectException(\Error::class);

        $config = new ChunkingConfig(chunkOverlap: 50);
        $config->chunkOverlap = 100;
    }

    #[Test]
    public function it_enforces_readonly_on_respect_sentences_property(): void
    {
        $this->expectException(\Error::class);

        $config = new ChunkingConfig(respectSentences: true);
        $config->respectSentences = false;
    }

    #[Test]
    public function it_creates_from_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'chunk_');
        if ($tempFile === false) {
            $this->markTestSkipped('Unable to create temporary file');
        }

        try {
            file_put_contents($tempFile, json_encode([
                'max_chunk_size' => 512,
                'chunk_overlap' => 50,
            ]));

            $config = ChunkingConfig::fromFile($tempFile);

            $this->assertSame(512, $config->maxChunkSize);
            $this->assertSame(50, $config->chunkOverlap);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    #[Test]
    public function it_throws_when_file_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        ChunkingConfig::fromFile('/nonexistent/path/config.json');
    }

    #[Test]
    public function it_handles_type_coercion_for_max_chunk_size(): void
    {
        $data = ['max_chunk_size' => '1024'];
        $config = ChunkingConfig::fromArray($data);

        $this->assertIsInt($config->maxChunkSize);
        $this->assertSame(1024, $config->maxChunkSize);
    }

    #[Test]
    public function it_handles_type_coercion_for_chunk_overlap(): void
    {
        $data = ['chunk_overlap' => '100'];
        $config = ChunkingConfig::fromArray($data);

        $this->assertIsInt($config->chunkOverlap);
        $this->assertSame(100, $config->chunkOverlap);
    }

    #[Test]
    public function it_handles_type_coercion_for_respect_sentences(): void
    {
        $data = ['respect_sentences' => 0];
        $config = ChunkingConfig::fromArray($data);

        $this->assertIsBool($config->respectSentences);
        $this->assertFalse($config->respectSentences);
    }

    #[Test]
    public function it_json_output_is_prettified(): void
    {
        $config = new ChunkingConfig();
        $json = $config->toJson();

        $this->assertStringContainsString("\n", $json);
        $this->assertStringContainsString('  ', $json);
    }
}
