<?php

declare(strict_types=1);

namespace Kreuzberg\Tests\Unit\Config;

use Kreuzberg\Config\TesseractConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TesseractConfig readonly class.
 *
 * Tests construction, serialization, factory methods, readonly enforcement,
 * and handling of optional nullable properties. Validates that Tesseract
 * configuration can be properly created, serialized, and maintained in a readonly state.
 *
 * Test Coverage:
 * - Construction with default values
 * - Construction with custom values
 * - toArray() serialization with optional field inclusion
 * - fromArray() factory method
 * - fromJson() factory method
 * - toJson() serialization
 * - Readonly enforcement (modification prevention)
 * - Null handling
 * - Invalid JSON handling
 * - Round-trip serialization
 */
#[CoversClass(TesseractConfig::class)]
#[Group('unit')]
#[Group('config')]
final class TesseractConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_default_values(): void
    {
        $config = new TesseractConfig();

        $this->assertNull($config->psm);
        $this->assertNull($config->oem);
        $this->assertFalse($config->enableTableDetection);
        $this->assertNull($config->tesseditCharWhitelist);
        $this->assertNull($config->tesseditCharBlacklist);
    }

    #[Test]
    public function it_creates_with_custom_values(): void
    {
        $config = new TesseractConfig(
            psm: 6,
            oem: 1,
            enableTableDetection: true,
            tesseditCharWhitelist: '0123456789',
            tesseditCharBlacklist: '!@#$',
        );

        $this->assertSame(6, $config->psm);
        $this->assertSame(1, $config->oem);
        $this->assertTrue($config->enableTableDetection);
        $this->assertSame('0123456789', $config->tesseditCharWhitelist);
        $this->assertSame('!@#$', $config->tesseditCharBlacklist);
    }

    #[Test]
    public function it_serializes_to_array_with_only_non_default_values(): void
    {
        $config = new TesseractConfig(psm: 6, enableTableDetection: false);
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('psm', $array);
        $this->assertSame(6, $array['psm']);
        $this->assertArrayNotHasKey('oem', $array);
        // enableTableDetection is included even if false because array_filter only removes null values
        $this->assertArrayHasKey('enable_table_detection', $array);
        $this->assertFalse($array['enable_table_detection']);
    }

    #[Test]
    public function it_includes_enable_table_detection_true_in_array(): void
    {
        $config = new TesseractConfig(enableTableDetection: true);
        $array = $config->toArray();

        $this->assertTrue($array['enable_table_detection']);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $config = TesseractConfig::fromArray([]);

        $this->assertNull($config->psm);
        $this->assertNull($config->oem);
        $this->assertFalse($config->enableTableDetection);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'psm' => 11,
            'oem' => 2,
            'enable_table_detection' => true,
            'tessedit_char_whitelist' => 'abcdef',
            'tessedit_char_blacklist' => 'xyz',
        ];
        $config = TesseractConfig::fromArray($data);

        $this->assertSame(11, $config->psm);
        $this->assertSame(2, $config->oem);
        $this->assertTrue($config->enableTableDetection);
        $this->assertSame('abcdef', $config->tesseditCharWhitelist);
        $this->assertSame('xyz', $config->tesseditCharBlacklist);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $config = new TesseractConfig(
            psm: 3,
            oem: 1,
            enableTableDetection: true,
        );
        $json = $config->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertSame(3, $decoded['psm']);
        $this->assertSame(1, $decoded['oem']);
        $this->assertTrue($decoded['enable_table_detection']);
    }

    #[Test]
    public function it_creates_from_json(): void
    {
        $json = json_encode([
            'psm' => 6,
            'oem' => 3,
            'enable_table_detection' => false,
        ]);
        $config = TesseractConfig::fromJson($json);

        $this->assertSame(6, $config->psm);
        $this->assertSame(3, $config->oem);
        $this->assertFalse($config->enableTableDetection);
    }

    #[Test]
    public function it_round_trips_through_json(): void
    {
        $original = new TesseractConfig(
            psm: 6,
            oem: 1,
            enableTableDetection: true,
            tesseditCharWhitelist: '0-9',
            tesseditCharBlacklist: 'xyz',
        );

        $json = $original->toJson();
        $restored = TesseractConfig::fromJson($json);

        $this->assertSame($original->psm, $restored->psm);
        $this->assertSame($original->oem, $restored->oem);
        $this->assertSame($original->enableTableDetection, $restored->enableTableDetection);
        $this->assertSame($original->tesseditCharWhitelist, $restored->tesseditCharWhitelist);
        $this->assertSame($original->tesseditCharBlacklist, $restored->tesseditCharBlacklist);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        TesseractConfig::fromJson('{ invalid }');
    }

    #[Test]
    public function it_enforces_readonly_on_psm_property(): void
    {
        $this->expectException(\Error::class);

        $config = new TesseractConfig(psm: 6);
        $config->psm = 3;
    }

    #[Test]
    public function it_enforces_readonly_on_enable_table_detection_property(): void
    {
        $this->expectException(\Error::class);

        $config = new TesseractConfig(enableTableDetection: true);
        $config->enableTableDetection = false;
    }

    #[Test]
    public function it_creates_from_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tess_');
        if ($tempFile === false) {
            $this->markTestSkipped('Unable to create temporary file');
        }

        try {
            file_put_contents($tempFile, json_encode([
                'psm' => 6,
                'oem' => 1,
            ]));

            $config = TesseractConfig::fromFile($tempFile);

            $this->assertSame(6, $config->psm);
            $this->assertSame(1, $config->oem);
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

        TesseractConfig::fromFile('/nonexistent/path/config.json');
    }

    #[Test]
    public function it_handles_type_coercion_for_psm(): void
    {
        $data = ['psm' => '6'];
        $config = TesseractConfig::fromArray($data);

        $this->assertIsInt($config->psm);
        $this->assertSame(6, $config->psm);
    }

    #[Test]
    public function it_handles_type_coercion_for_oem(): void
    {
        $data = ['oem' => '3'];
        $config = TesseractConfig::fromArray($data);

        $this->assertIsInt($config->oem);
        $this->assertSame(3, $config->oem);
    }

    #[Test]
    public function it_handles_type_coercion_for_enable_table_detection(): void
    {
        $data = ['enable_table_detection' => 1];
        $config = TesseractConfig::fromArray($data);

        $this->assertIsBool($config->enableTableDetection);
        $this->assertTrue($config->enableTableDetection);
    }
}
