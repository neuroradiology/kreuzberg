<?php

declare(strict_types=1);

namespace Kreuzberg\Tests\Unit\Config;

use Kreuzberg\Config\LanguageDetectionConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LanguageDetectionConfig readonly class.
 *
 * Tests construction, serialization, factory methods, readonly enforcement,
 * and handling of boolean and nullable numeric properties.
 *
 * Test Coverage:
 * - Construction with default values
 * - Construction with custom values
 * - toArray() serialization with optional field inclusion
 * - fromArray() factory method
 * - fromJson() factory method
 * - toJson() serialization
 * - Readonly enforcement
 * - Threshold handling
 * - Null handling
 * - Invalid JSON handling
 * - Round-trip serialization
 */
#[CoversClass(LanguageDetectionConfig::class)]
#[Group('unit')]
#[Group('config')]
final class LanguageDetectionConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_default_values(): void
    {
        $config = new LanguageDetectionConfig();

        $this->assertFalse($config->enabled);
        $this->assertNull($config->maxLanguages);
        $this->assertNull($config->confidenceThreshold);
    }

    #[Test]
    public function it_creates_with_custom_values(): void
    {
        $config = new LanguageDetectionConfig(
            enabled: true,
            maxLanguages: 5,
            confidenceThreshold: 0.8,
        );

        $this->assertTrue($config->enabled);
        $this->assertSame(5, $config->maxLanguages);
        $this->assertSame(0.8, $config->confidenceThreshold);
    }

    #[Test]
    public function it_serializes_to_array_with_only_enabled_by_default(): void
    {
        $config = new LanguageDetectionConfig(enabled: false);
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertFalse($array['enabled']);
        $this->assertArrayNotHasKey('max_languages', $array);
        $this->assertArrayNotHasKey('confidence_threshold', $array);
    }

    #[Test]
    public function it_includes_optional_fields_when_set(): void
    {
        $config = new LanguageDetectionConfig(
            enabled: true,
            maxLanguages: 3,
            confidenceThreshold: 0.9,
        );
        $array = $config->toArray();

        $this->assertTrue($array['enabled']);
        $this->assertArrayHasKey('max_languages', $array);
        $this->assertArrayHasKey('confidence_threshold', $array);
        $this->assertSame(3, $array['max_languages']);
        $this->assertSame(0.9, $array['confidence_threshold']);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $config = LanguageDetectionConfig::fromArray([]);

        $this->assertFalse($config->enabled);
        $this->assertNull($config->maxLanguages);
        $this->assertNull($config->confidenceThreshold);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'enabled' => true,
            'max_languages' => 10,
            'confidence_threshold' => 0.75,
        ];
        $config = LanguageDetectionConfig::fromArray($data);

        $this->assertTrue($config->enabled);
        $this->assertSame(10, $config->maxLanguages);
        $this->assertSame(0.75, $config->confidenceThreshold);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $config = new LanguageDetectionConfig(
            enabled: true,
            maxLanguages: 7,
            confidenceThreshold: 0.85,
        );
        $json = $config->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['enabled']);
        $this->assertSame(7, $decoded['max_languages']);
        $this->assertSame(0.85, $decoded['confidence_threshold']);
    }

    #[Test]
    public function it_creates_from_json(): void
    {
        $json = json_encode([
            'enabled' => false,
            'max_languages' => 2,
            'confidence_threshold' => 0.5,
        ]);
        $config = LanguageDetectionConfig::fromJson($json);

        $this->assertFalse($config->enabled);
        $this->assertSame(2, $config->maxLanguages);
        $this->assertSame(0.5, $config->confidenceThreshold);
    }

    #[Test]
    public function it_round_trips_through_json(): void
    {
        $original = new LanguageDetectionConfig(
            enabled: true,
            maxLanguages: 8,
            confidenceThreshold: 0.92,
        );

        $json = $original->toJson();
        $restored = LanguageDetectionConfig::fromJson($json);

        $this->assertSame($original->enabled, $restored->enabled);
        $this->assertSame($original->maxLanguages, $restored->maxLanguages);
        $this->assertSame($original->confidenceThreshold, $restored->confidenceThreshold);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        LanguageDetectionConfig::fromJson('{ invalid }');
    }

    #[Test]
    public function it_enforces_readonly_on_enabled_property(): void
    {
        $this->expectException(\Error::class);

        $config = new LanguageDetectionConfig(enabled: true);
        $config->enabled = false;
    }

    #[Test]
    public function it_enforces_readonly_on_max_languages_property(): void
    {
        $this->expectException(\Error::class);

        $config = new LanguageDetectionConfig(maxLanguages: 5);
        $config->maxLanguages = 10;
    }

    #[Test]
    public function it_enforces_readonly_on_confidence_threshold_property(): void
    {
        $this->expectException(\Error::class);

        $config = new LanguageDetectionConfig(confidenceThreshold: 0.8);
        $config->confidenceThreshold = 0.9;
    }

    #[Test]
    public function it_creates_from_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'langdetect_');
        if ($tempFile === false) {
            $this->markTestSkipped('Unable to create temporary file');
        }

        try {
            file_put_contents($tempFile, json_encode([
                'enabled' => true,
                'max_languages' => 5,
            ]));

            $config = LanguageDetectionConfig::fromFile($tempFile);

            $this->assertTrue($config->enabled);
            $this->assertSame(5, $config->maxLanguages);
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

        LanguageDetectionConfig::fromFile('/nonexistent/path/config.json');
    }

    #[Test]
    public function it_handles_type_coercion_for_enabled(): void
    {
        $data = ['enabled' => 1];
        $config = LanguageDetectionConfig::fromArray($data);

        $this->assertIsBool($config->enabled);
        $this->assertTrue($config->enabled);
    }

    #[Test]
    public function it_handles_type_coercion_for_max_languages(): void
    {
        $data = ['max_languages' => '5'];
        $config = LanguageDetectionConfig::fromArray($data);

        $this->assertIsInt($config->maxLanguages);
        $this->assertSame(5, $config->maxLanguages);
    }

    #[Test]
    public function it_handles_type_coercion_for_confidence_threshold(): void
    {
        $data = ['confidence_threshold' => '0.85'];
        $config = LanguageDetectionConfig::fromArray($data);

        $this->assertIsFloat($config->confidenceThreshold);
        $this->assertSame(0.85, $config->confidenceThreshold);
    }

    #[Test]
    public function it_supports_enabled_without_languages(): void
    {
        $config = new LanguageDetectionConfig(enabled: true);

        $this->assertTrue($config->enabled);
        $this->assertNull($config->maxLanguages);
        $this->assertNull($config->confidenceThreshold);
    }

    #[Test]
    public function it_supports_zero_confidence_threshold(): void
    {
        $config = new LanguageDetectionConfig(confidenceThreshold: 0.0);

        $this->assertSame(0.0, $config->confidenceThreshold);
        $array = $config->toArray();
        $this->assertArrayHasKey('confidence_threshold', $array);
        $this->assertSame(0.0, $array['confidence_threshold']);
    }

    #[Test]
    public function it_supports_one_confidence_threshold(): void
    {
        $config = new LanguageDetectionConfig(confidenceThreshold: 1.0);

        $this->assertSame(1.0, $config->confidenceThreshold);
        $array = $config->toArray();
        $this->assertSame(1.0, $array['confidence_threshold']);
    }
}
