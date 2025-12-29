<?php

declare(strict_types=1);

namespace Kreuzberg\Tests\Unit\Config;

use Kreuzberg\Config\ImagePreprocessingConfig;
use Kreuzberg\Config\OcrConfig;
use Kreuzberg\Config\TesseractConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OcrConfig readonly class.
 *
 * Tests construction, serialization, factory methods, readonly enforcement,
 * and handling of nested configuration values. Validates that OCR configuration
 * with nested TesseractConfig and ImagePreprocessingConfig can be properly created,
 * serialized, and maintained in a readonly state.
 *
 * Test Coverage:
 * - Construction with default values
 * - Construction with custom values
 * - toArray() serialization with optional field inclusion
 * - fromArray() factory method with nested structures
 * - fromJson() factory method with various data types
 * - toJson() serialization
 * - Readonly enforcement (modification prevention)
 * - Nested configuration handling
 * - Invalid JSON handling
 * - Round-trip serialization
 */
#[CoversClass(OcrConfig::class)]
#[Group('unit')]
#[Group('config')]
final class OcrConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_default_values(): void
    {
        $config = new OcrConfig();

        $this->assertSame('tesseract', $config->backend);
        $this->assertSame('eng', $config->language);
        $this->assertNull($config->tesseractConfig);
        $this->assertNull($config->imagePreprocessing);
    }

    #[Test]
    public function it_creates_with_custom_values(): void
    {
        $tesseractConfig = new TesseractConfig(psm: 6, oem: 1);
        $imagePreprocessing = new ImagePreprocessingConfig(autoRotate: true);

        $config = new OcrConfig(
            backend: 'easyocr',
            language: 'fra',
            tesseractConfig: $tesseractConfig,
            imagePreprocessing: $imagePreprocessing,
        );

        $this->assertSame('easyocr', $config->backend);
        $this->assertSame('fra', $config->language);
        $this->assertSame($tesseractConfig, $config->tesseractConfig);
        $this->assertSame($imagePreprocessing, $config->imagePreprocessing);
    }

    #[Test]
    public function it_serializes_to_array_with_only_backend_and_language_by_default(): void
    {
        $config = new OcrConfig();
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertSame('tesseract', $array['backend']);
        $this->assertSame('eng', $array['language']);
        $this->assertArrayNotHasKey('tesseract_config', $array);
        $this->assertArrayNotHasKey('image_preprocessing', $array);
    }

    #[Test]
    public function it_includes_nested_configs_in_array_when_set(): void
    {
        $tesseractConfig = new TesseractConfig(psm: 6);
        $imagePreprocessing = new ImagePreprocessingConfig(denoise: true);

        $config = new OcrConfig(
            backend: 'tesseract',
            language: 'deu',
            tesseractConfig: $tesseractConfig,
            imagePreprocessing: $imagePreprocessing,
        );
        $array = $config->toArray();

        $this->assertArrayHasKey('tesseract_config', $array);
        $this->assertArrayHasKey('image_preprocessing', $array);
        $this->assertIsArray($array['tesseract_config']);
        $this->assertIsArray($array['image_preprocessing']);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $config = OcrConfig::fromArray([]);

        $this->assertSame('tesseract', $config->backend);
        $this->assertSame('eng', $config->language);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'backend' => 'paddleocr',
            'language' => 'spa',
            'tesseract_config' => ['psm' => 6],
            'image_preprocessing' => ['auto_rotate' => true, 'denoise' => true],
        ];
        $config = OcrConfig::fromArray($data);

        $this->assertSame('paddleocr', $config->backend);
        $this->assertSame('spa', $config->language);
        $this->assertNotNull($config->tesseractConfig);
        $this->assertNotNull($config->imagePreprocessing);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $config = new OcrConfig(backend: 'tesseract', language: 'ita');
        $json = $config->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertSame('tesseract', $decoded['backend']);
        $this->assertSame('ita', $decoded['language']);
    }

    #[Test]
    public function it_creates_from_json(): void
    {
        $json = json_encode([
            'backend' => 'easyocr',
            'language' => 'rus',
        ]);
        $config = OcrConfig::fromJson($json);

        $this->assertSame('easyocr', $config->backend);
        $this->assertSame('rus', $config->language);
    }

    #[Test]
    public function it_round_trips_through_json(): void
    {
        $original = new OcrConfig(
            backend: 'tesseract',
            language: 'jpn',
            tesseractConfig: new TesseractConfig(psm: 11),
        );

        $json = $original->toJson();
        $restored = OcrConfig::fromJson($json);

        $this->assertSame($original->backend, $restored->backend);
        $this->assertSame($original->language, $restored->language);
        $this->assertNotNull($restored->tesseractConfig);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        OcrConfig::fromJson('{ broken json');
    }

    #[Test]
    public function it_enforces_readonly_on_backend_property(): void
    {
        $this->expectException(\Error::class);

        $config = new OcrConfig(backend: 'tesseract');
        $config->backend = 'easyocr';
    }

    #[Test]
    public function it_enforces_readonly_on_language_property(): void
    {
        $this->expectException(\Error::class);

        $config = new OcrConfig(language: 'eng');
        $config->language = 'fra';
    }

    #[Test]
    public function it_creates_from_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
        if ($tempFile === false) {
            $this->markTestSkipped('Unable to create temporary file');
        }

        try {
            file_put_contents($tempFile, json_encode([
                'backend' => 'tesseract',
                'language' => 'deu',
            ]));

            $config = OcrConfig::fromFile($tempFile);

            $this->assertSame('tesseract', $config->backend);
            $this->assertSame('deu', $config->language);
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

        OcrConfig::fromFile('/nonexistent/path/config.json');
    }

    #[Test]
    public function it_handles_type_coercion_for_backend(): void
    {
        $data = ['backend' => 123];
        $config = OcrConfig::fromArray($data);

        $this->assertIsString($config->backend);
        $this->assertSame('123', $config->backend);
    }

    #[Test]
    public function it_handles_type_coercion_for_language(): void
    {
        $data = ['language' => 456];
        $config = OcrConfig::fromArray($data);

        $this->assertIsString($config->language);
    }
}
