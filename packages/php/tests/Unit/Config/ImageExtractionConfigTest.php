<?php

declare(strict_types=1);

namespace Kreuzberg\Tests\Unit\Config;

use Kreuzberg\Config\ImageExtractionConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ImageExtractionConfig readonly class.
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
 * - Null handling
 * - Invalid JSON handling
 * - Round-trip serialization
 */
#[CoversClass(ImageExtractionConfig::class)]
#[Group('unit')]
#[Group('config')]
final class ImageExtractionConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_default_values(): void
    {
        $config = new ImageExtractionConfig();

        $this->assertFalse($config->extractImages);
        $this->assertFalse($config->performOcr);
        $this->assertNull($config->minWidth);
        $this->assertNull($config->minHeight);
    }

    #[Test]
    public function it_creates_with_custom_values(): void
    {
        $config = new ImageExtractionConfig(
            extractImages: true,
            performOcr: true,
            minWidth: 100,
            minHeight: 200,
        );

        $this->assertTrue($config->extractImages);
        $this->assertTrue($config->performOcr);
        $this->assertSame(100, $config->minWidth);
        $this->assertSame(200, $config->minHeight);
    }

    #[Test]
    public function it_serializes_to_array_with_only_non_null_values(): void
    {
        $config = new ImageExtractionConfig(extractImages: true);
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertTrue($array['extract_images']);
        $this->assertFalse($array['perform_ocr']);
        $this->assertArrayNotHasKey('min_width', $array);
        $this->assertArrayNotHasKey('min_height', $array);
    }

    #[Test]
    public function it_includes_dimensions_in_array_when_set(): void
    {
        $config = new ImageExtractionConfig(
            extractImages: true,
            minWidth: 150,
            minHeight: 250,
        );
        $array = $config->toArray();

        $this->assertArrayHasKey('min_width', $array);
        $this->assertArrayHasKey('min_height', $array);
        $this->assertSame(150, $array['min_width']);
        $this->assertSame(250, $array['min_height']);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $config = ImageExtractionConfig::fromArray([]);

        $this->assertFalse($config->extractImages);
        $this->assertFalse($config->performOcr);
        $this->assertNull($config->minWidth);
        $this->assertNull($config->minHeight);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'extract_images' => true,
            'perform_ocr' => true,
            'min_width' => 300,
            'min_height' => 400,
        ];
        $config = ImageExtractionConfig::fromArray($data);

        $this->assertTrue($config->extractImages);
        $this->assertTrue($config->performOcr);
        $this->assertSame(300, $config->minWidth);
        $this->assertSame(400, $config->minHeight);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $config = new ImageExtractionConfig(
            extractImages: true,
            performOcr: false,
            minWidth: 200,
        );
        $json = $config->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['extract_images']);
        $this->assertFalse($decoded['perform_ocr']);
        $this->assertSame(200, $decoded['min_width']);
    }

    #[Test]
    public function it_creates_from_json(): void
    {
        $json = json_encode([
            'extract_images' => false,
            'perform_ocr' => true,
            'min_width' => 150,
            'min_height' => 150,
        ]);
        $config = ImageExtractionConfig::fromJson($json);

        $this->assertFalse($config->extractImages);
        $this->assertTrue($config->performOcr);
        $this->assertSame(150, $config->minWidth);
        $this->assertSame(150, $config->minHeight);
    }

    #[Test]
    public function it_round_trips_through_json(): void
    {
        $original = new ImageExtractionConfig(
            extractImages: true,
            performOcr: true,
            minWidth: 500,
            minHeight: 600,
        );

        $json = $original->toJson();
        $restored = ImageExtractionConfig::fromJson($json);

        $this->assertSame($original->extractImages, $restored->extractImages);
        $this->assertSame($original->performOcr, $restored->performOcr);
        $this->assertSame($original->minWidth, $restored->minWidth);
        $this->assertSame($original->minHeight, $restored->minHeight);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        ImageExtractionConfig::fromJson('{ invalid }');
    }

    #[Test]
    public function it_enforces_readonly_on_extract_images_property(): void
    {
        $this->expectException(\Error::class);

        $config = new ImageExtractionConfig(extractImages: true);
        $config->extractImages = false;
    }

    #[Test]
    public function it_enforces_readonly_on_min_width_property(): void
    {
        $this->expectException(\Error::class);

        $config = new ImageExtractionConfig(minWidth: 100);
        $config->minWidth = 200;
    }

    #[Test]
    public function it_creates_from_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'img_');
        if ($tempFile === false) {
            $this->markTestSkipped('Unable to create temporary file');
        }

        try {
            file_put_contents($tempFile, json_encode([
                'extract_images' => true,
                'min_width' => 100,
            ]));

            $config = ImageExtractionConfig::fromFile($tempFile);

            $this->assertTrue($config->extractImages);
            $this->assertSame(100, $config->minWidth);
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

        ImageExtractionConfig::fromFile('/nonexistent/path/config.json');
    }

    #[Test]
    public function it_handles_type_coercion_for_extract_images(): void
    {
        $data = ['extract_images' => 1];
        $config = ImageExtractionConfig::fromArray($data);

        $this->assertIsBool($config->extractImages);
        $this->assertTrue($config->extractImages);
    }

    #[Test]
    public function it_handles_type_coercion_for_min_width(): void
    {
        $data = ['min_width' => '250'];
        $config = ImageExtractionConfig::fromArray($data);

        $this->assertIsInt($config->minWidth);
        $this->assertSame(250, $config->minWidth);
    }

    #[Test]
    public function it_handles_empty_string_as_null(): void
    {
        $config = new ImageExtractionConfig(minWidth: null, minHeight: null);

        $this->assertNull($config->minWidth);
        $this->assertNull($config->minHeight);
    }
}
