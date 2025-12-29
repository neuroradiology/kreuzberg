<?php

declare(strict_types=1);

namespace Kreuzberg\Tests\Unit\Config;

use Kreuzberg\Config\PdfConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PdfConfig readonly class.
 *
 * Tests construction, serialization, factory methods, readonly enforcement,
 * and handling of boolean and nullable integer properties.
 *
 * Test Coverage:
 * - Construction with default values
 * - Construction with custom values
 * - toArray() serialization with optional field inclusion
 * - fromArray() factory method
 * - fromJson() factory method
 * - toJson() serialization
 * - Readonly enforcement
 * - Page range handling
 * - Null handling
 * - Invalid JSON handling
 * - Round-trip serialization
 */
#[CoversClass(PdfConfig::class)]
#[Group('unit')]
#[Group('config')]
final class PdfConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_default_values(): void
    {
        $config = new PdfConfig();

        $this->assertFalse($config->extractImages);
        $this->assertTrue($config->extractMetadata);
        $this->assertFalse($config->ocrFallback);
        $this->assertNull($config->startPage);
        $this->assertNull($config->endPage);
    }

    #[Test]
    public function it_creates_with_custom_values(): void
    {
        $config = new PdfConfig(
            extractImages: true,
            extractMetadata: false,
            ocrFallback: true,
            startPage: 1,
            endPage: 10,
        );

        $this->assertTrue($config->extractImages);
        $this->assertFalse($config->extractMetadata);
        $this->assertTrue($config->ocrFallback);
        $this->assertSame(1, $config->startPage);
        $this->assertSame(10, $config->endPage);
    }

    #[Test]
    public function it_serializes_to_array_with_only_non_default_values(): void
    {
        $config = new PdfConfig(extractImages: true);
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertTrue($array['extract_images']);
        $this->assertTrue($array['extract_metadata']);
        $this->assertFalse($array['ocr_fallback']);
        $this->assertArrayNotHasKey('start_page', $array);
        $this->assertArrayNotHasKey('end_page', $array);
    }

    #[Test]
    public function it_includes_page_range_in_array_when_set(): void
    {
        $config = new PdfConfig(
            startPage: 5,
            endPage: 15,
        );
        $array = $config->toArray();

        $this->assertArrayHasKey('start_page', $array);
        $this->assertArrayHasKey('end_page', $array);
        $this->assertSame(5, $array['start_page']);
        $this->assertSame(15, $array['end_page']);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $config = PdfConfig::fromArray([]);

        $this->assertFalse($config->extractImages);
        $this->assertTrue($config->extractMetadata);
        $this->assertFalse($config->ocrFallback);
        $this->assertNull($config->startPage);
        $this->assertNull($config->endPage);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'extract_images' => true,
            'extract_metadata' => false,
            'ocr_fallback' => true,
            'start_page' => 2,
            'end_page' => 50,
        ];
        $config = PdfConfig::fromArray($data);

        $this->assertTrue($config->extractImages);
        $this->assertFalse($config->extractMetadata);
        $this->assertTrue($config->ocrFallback);
        $this->assertSame(2, $config->startPage);
        $this->assertSame(50, $config->endPage);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $config = new PdfConfig(
            extractImages: true,
            extractMetadata: true,
            ocrFallback: false,
            startPage: 1,
            endPage: 20,
        );
        $json = $config->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['extract_images']);
        $this->assertTrue($decoded['extract_metadata']);
        $this->assertFalse($decoded['ocr_fallback']);
        $this->assertSame(1, $decoded['start_page']);
        $this->assertSame(20, $decoded['end_page']);
    }

    #[Test]
    public function it_creates_from_json(): void
    {
        $json = json_encode([
            'extract_images' => false,
            'extract_metadata' => true,
            'ocr_fallback' => true,
            'start_page' => 3,
            'end_page' => 25,
        ]);
        $config = PdfConfig::fromJson($json);

        $this->assertFalse($config->extractImages);
        $this->assertTrue($config->extractMetadata);
        $this->assertTrue($config->ocrFallback);
        $this->assertSame(3, $config->startPage);
        $this->assertSame(25, $config->endPage);
    }

    #[Test]
    public function it_round_trips_through_json(): void
    {
        $original = new PdfConfig(
            extractImages: true,
            extractMetadata: false,
            ocrFallback: true,
            startPage: 10,
            endPage: 100,
        );

        $json = $original->toJson();
        $restored = PdfConfig::fromJson($json);

        $this->assertSame($original->extractImages, $restored->extractImages);
        $this->assertSame($original->extractMetadata, $restored->extractMetadata);
        $this->assertSame($original->ocrFallback, $restored->ocrFallback);
        $this->assertSame($original->startPage, $restored->startPage);
        $this->assertSame($original->endPage, $restored->endPage);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        PdfConfig::fromJson('{ invalid }');
    }

    #[Test]
    public function it_enforces_readonly_on_extract_images_property(): void
    {
        $this->expectException(\Error::class);

        $config = new PdfConfig(extractImages: true);
        $config->extractImages = false;
    }

    #[Test]
    public function it_enforces_readonly_on_ocr_fallback_property(): void
    {
        $this->expectException(\Error::class);

        $config = new PdfConfig(ocrFallback: true);
        $config->ocrFallback = false;
    }

    #[Test]
    public function it_enforces_readonly_on_start_page_property(): void
    {
        $this->expectException(\Error::class);

        $config = new PdfConfig(startPage: 1);
        $config->startPage = 10;
    }

    #[Test]
    public function it_creates_from_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
        if ($tempFile === false) {
            $this->markTestSkipped('Unable to create temporary file');
        }

        try {
            file_put_contents($tempFile, json_encode([
                'extract_images' => true,
                'start_page' => 1,
                'end_page' => 10,
            ]));

            $config = PdfConfig::fromFile($tempFile);

            $this->assertTrue($config->extractImages);
            $this->assertSame(1, $config->startPage);
            $this->assertSame(10, $config->endPage);
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

        PdfConfig::fromFile('/nonexistent/path/config.json');
    }

    #[Test]
    public function it_handles_type_coercion_for_extract_images(): void
    {
        $data = ['extract_images' => 1];
        $config = PdfConfig::fromArray($data);

        $this->assertIsBool($config->extractImages);
        $this->assertTrue($config->extractImages);
    }

    #[Test]
    public function it_handles_type_coercion_for_start_page(): void
    {
        $data = ['start_page' => '5'];
        $config = PdfConfig::fromArray($data);

        $this->assertIsInt($config->startPage);
        $this->assertSame(5, $config->startPage);
    }

    #[Test]
    public function it_handles_type_coercion_for_end_page(): void
    {
        $data = ['end_page' => '50'];
        $config = PdfConfig::fromArray($data);

        $this->assertIsInt($config->endPage);
        $this->assertSame(50, $config->endPage);
    }

    #[Test]
    public function it_supports_only_start_page(): void
    {
        $config = new PdfConfig(startPage: 1);

        $this->assertSame(1, $config->startPage);
        $this->assertNull($config->endPage);

        $array = $config->toArray();
        $this->assertArrayHasKey('start_page', $array);
        $this->assertArrayNotHasKey('end_page', $array);
    }

    #[Test]
    public function it_supports_only_end_page(): void
    {
        $config = new PdfConfig(endPage: 100);

        $this->assertNull($config->startPage);
        $this->assertSame(100, $config->endPage);

        $array = $config->toArray();
        $this->assertArrayNotHasKey('start_page', $array);
        $this->assertArrayHasKey('end_page', $array);
    }
}
