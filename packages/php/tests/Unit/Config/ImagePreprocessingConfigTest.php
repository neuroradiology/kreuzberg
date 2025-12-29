<?php

declare(strict_types=1);

namespace Kreuzberg\Tests\Unit\Config;

use Kreuzberg\Config\ImagePreprocessingConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ImagePreprocessingConfig readonly class.
 *
 * Tests construction, serialization, factory methods, readonly enforcement,
 * and handling of mixed property types (int, bool, string, float, null).
 *
 * Test Coverage:
 * - Construction with default values
 * - Construction with custom values
 * - toArray() serialization with optional field inclusion
 * - fromArray() factory method
 * - fromJson() factory method
 * - toJson() serialization
 * - Readonly enforcement
 * - Type coercion
 * - Null handling
 * - Invalid JSON handling
 * - Round-trip serialization
 */
#[CoversClass(ImagePreprocessingConfig::class)]
#[Group('unit')]
#[Group('config')]
final class ImagePreprocessingConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_default_values(): void
    {
        $config = new ImagePreprocessingConfig();

        $this->assertNull($config->targetDpi);
        $this->assertFalse($config->autoRotate);
        $this->assertFalse($config->deskew);
        $this->assertNull($config->binarizationMethod);
        $this->assertFalse($config->denoise);
        $this->assertFalse($config->sharpen);
        $this->assertNull($config->contrastAdjustment);
        $this->assertNull($config->brightnessAdjustment);
    }

    #[Test]
    public function it_creates_with_custom_values(): void
    {
        $config = new ImagePreprocessingConfig(
            targetDpi: 300,
            autoRotate: true,
            deskew: true,
            binarizationMethod: 'otsu',
            denoise: true,
            sharpen: true,
            contrastAdjustment: 1.5,
            brightnessAdjustment: 0.8,
        );

        $this->assertSame(300, $config->targetDpi);
        $this->assertTrue($config->autoRotate);
        $this->assertTrue($config->deskew);
        $this->assertSame('otsu', $config->binarizationMethod);
        $this->assertTrue($config->denoise);
        $this->assertTrue($config->sharpen);
        $this->assertSame(1.5, $config->contrastAdjustment);
        $this->assertSame(0.8, $config->brightnessAdjustment);
    }

    #[Test]
    public function it_serializes_to_array_with_only_non_null_values(): void
    {
        $config = new ImagePreprocessingConfig(
            autoRotate: true,
            targetDpi: 200,
        );
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('auto_rotate', $array);
        $this->assertArrayHasKey('target_dpi', $array);
        $this->assertTrue($array['auto_rotate']);
        $this->assertSame(200, $array['target_dpi']);
        $this->assertArrayNotHasKey('binarization_method', $array);
        $this->assertArrayNotHasKey('contrast_adjustment', $array);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $config = ImagePreprocessingConfig::fromArray([]);

        $this->assertNull($config->targetDpi);
        $this->assertFalse($config->autoRotate);
        $this->assertFalse($config->deskew);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'target_dpi' => 600,
            'auto_rotate' => true,
            'deskew' => true,
            'binarization_method' => 'adaptive',
            'denoise' => true,
            'sharpen' => false,
            'contrast_adjustment' => 2.0,
            'brightness_adjustment' => -0.5,
        ];
        $config = ImagePreprocessingConfig::fromArray($data);

        $this->assertSame(600, $config->targetDpi);
        $this->assertTrue($config->autoRotate);
        $this->assertTrue($config->deskew);
        $this->assertSame('adaptive', $config->binarizationMethod);
        $this->assertTrue($config->denoise);
        $this->assertFalse($config->sharpen);
        $this->assertSame(2.0, $config->contrastAdjustment);
        $this->assertSame(-0.5, $config->brightnessAdjustment);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $config = new ImagePreprocessingConfig(
            targetDpi: 300,
            autoRotate: true,
            binarizationMethod: 'otsu',
            contrastAdjustment: 1.2,
        );
        $json = $config->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertSame(300, $decoded['target_dpi']);
        $this->assertTrue($decoded['auto_rotate']);
        $this->assertSame('otsu', $decoded['binarization_method']);
        $this->assertSame(1.2, $decoded['contrast_adjustment']);
    }

    #[Test]
    public function it_creates_from_json(): void
    {
        $json = json_encode([
            'target_dpi' => 150,
            'auto_rotate' => false,
            'deskew' => true,
            'denoise' => true,
        ]);
        $config = ImagePreprocessingConfig::fromJson($json);

        $this->assertSame(150, $config->targetDpi);
        $this->assertFalse($config->autoRotate);
        $this->assertTrue($config->deskew);
        $this->assertTrue($config->denoise);
    }

    #[Test]
    public function it_round_trips_through_json(): void
    {
        $original = new ImagePreprocessingConfig(
            targetDpi: 300,
            autoRotate: true,
            deskew: true,
            binarizationMethod: 'otsu',
            denoise: true,
            sharpen: false,
            contrastAdjustment: 1.5,
            brightnessAdjustment: 0.8,
        );

        $json = $original->toJson();
        $restored = ImagePreprocessingConfig::fromJson($json);

        $this->assertSame($original->targetDpi, $restored->targetDpi);
        $this->assertSame($original->autoRotate, $restored->autoRotate);
        $this->assertSame($original->deskew, $restored->deskew);
        $this->assertSame($original->binarizationMethod, $restored->binarizationMethod);
        $this->assertSame($original->denoise, $restored->denoise);
        $this->assertSame($original->sharpen, $restored->sharpen);
        $this->assertSame($original->contrastAdjustment, $restored->contrastAdjustment);
        $this->assertSame($original->brightnessAdjustment, $restored->brightnessAdjustment);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        ImagePreprocessingConfig::fromJson('{ invalid }');
    }

    #[Test]
    public function it_enforces_readonly_on_target_dpi_property(): void
    {
        $this->expectException(\Error::class);

        $config = new ImagePreprocessingConfig(targetDpi: 300);
        $config->targetDpi = 200;
    }

    #[Test]
    public function it_enforces_readonly_on_auto_rotate_property(): void
    {
        $this->expectException(\Error::class);

        $config = new ImagePreprocessingConfig(autoRotate: true);
        $config->autoRotate = false;
    }

    #[Test]
    public function it_enforces_readonly_on_contrast_adjustment_property(): void
    {
        $this->expectException(\Error::class);

        $config = new ImagePreprocessingConfig(contrastAdjustment: 1.5);
        $config->contrastAdjustment = 2.0;
    }

    #[Test]
    public function it_creates_from_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'imgprep_');
        if ($tempFile === false) {
            $this->markTestSkipped('Unable to create temporary file');
        }

        try {
            file_put_contents($tempFile, json_encode([
                'target_dpi' => 300,
                'auto_rotate' => true,
            ]));

            $config = ImagePreprocessingConfig::fromFile($tempFile);

            $this->assertSame(300, $config->targetDpi);
            $this->assertTrue($config->autoRotate);
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

        ImagePreprocessingConfig::fromFile('/nonexistent/path/config.json');
    }

    #[Test]
    public function it_handles_type_coercion_for_target_dpi(): void
    {
        $data = ['target_dpi' => '300'];
        $config = ImagePreprocessingConfig::fromArray($data);

        $this->assertIsInt($config->targetDpi);
        $this->assertSame(300, $config->targetDpi);
    }

    #[Test]
    public function it_handles_type_coercion_for_contrast_adjustment(): void
    {
        $data = ['contrast_adjustment' => '1.5'];
        $config = ImagePreprocessingConfig::fromArray($data);

        $this->assertIsFloat($config->contrastAdjustment);
        $this->assertSame(1.5, $config->contrastAdjustment);
    }

    #[Test]
    public function it_handles_negative_brightness_adjustment(): void
    {
        $config = new ImagePreprocessingConfig(brightnessAdjustment: -1.0);

        $this->assertSame(-1.0, $config->brightnessAdjustment);
        $array = $config->toArray();
        $this->assertArrayHasKey('brightness_adjustment', $array);
        $this->assertSame(-1.0, $array['brightness_adjustment']);
    }
}
