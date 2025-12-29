<?php

declare(strict_types=1);

namespace Kreuzberg\Tests\Unit\Config;

use Kreuzberg\Config\PageConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PageConfig readonly class.
 *
 * Tests construction, serialization, factory methods, readonly enforcement,
 * and handling of boolean and string properties with templating.
 *
 * Test Coverage:
 * - Construction with default values
 * - Construction with custom values
 * - toArray() serialization
 * - fromArray() factory method
 * - fromJson() factory method
 * - toJson() serialization
 * - Readonly enforcement
 * - Custom marker formats
 * - Invalid JSON handling
 * - Round-trip serialization
 */
#[CoversClass(PageConfig::class)]
#[Group('unit')]
#[Group('config')]
final class PageConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_default_values(): void
    {
        $config = new PageConfig();

        $this->assertFalse($config->extractPages);
        $this->assertFalse($config->insertPageMarkers);
        $this->assertSame('--- Page {page_num} ---', $config->markerFormat);
    }

    #[Test]
    public function it_creates_with_custom_values(): void
    {
        $config = new PageConfig(
            extractPages: true,
            insertPageMarkers: true,
            markerFormat: 'Page: {page_num}',
        );

        $this->assertTrue($config->extractPages);
        $this->assertTrue($config->insertPageMarkers);
        $this->assertSame('Page: {page_num}', $config->markerFormat);
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $config = new PageConfig(
            extractPages: true,
            insertPageMarkers: true,
            markerFormat: '[PAGE {page_num}]',
        );
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertTrue($array['extract_pages']);
        $this->assertTrue($array['insert_page_markers']);
        $this->assertSame('[PAGE {page_num}]', $array['marker_format']);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $config = PageConfig::fromArray([]);

        $this->assertFalse($config->extractPages);
        $this->assertFalse($config->insertPageMarkers);
        $this->assertSame('--- Page {page_num} ---', $config->markerFormat);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'extract_pages' => true,
            'insert_page_markers' => true,
            'marker_format' => 'Chapter {page_num}',
        ];
        $config = PageConfig::fromArray($data);

        $this->assertTrue($config->extractPages);
        $this->assertTrue($config->insertPageMarkers);
        $this->assertSame('Chapter {page_num}', $config->markerFormat);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $config = new PageConfig(
            extractPages: true,
            insertPageMarkers: false,
            markerFormat: 'Page {page_num}',
        );
        $json = $config->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['extract_pages']);
        $this->assertFalse($decoded['insert_page_markers']);
        $this->assertSame('Page {page_num}', $decoded['marker_format']);
    }

    #[Test]
    public function it_creates_from_json(): void
    {
        $json = json_encode([
            'extract_pages' => false,
            'insert_page_markers' => true,
            'marker_format' => '=== Page {page_num} ===',
        ]);
        $config = PageConfig::fromJson($json);

        $this->assertFalse($config->extractPages);
        $this->assertTrue($config->insertPageMarkers);
        $this->assertSame('=== Page {page_num} ===', $config->markerFormat);
    }

    #[Test]
    public function it_round_trips_through_json(): void
    {
        $original = new PageConfig(
            extractPages: true,
            insertPageMarkers: true,
            markerFormat: '[Page {page_num}]',
        );

        $json = $original->toJson();
        $restored = PageConfig::fromJson($json);

        $this->assertSame($original->extractPages, $restored->extractPages);
        $this->assertSame($original->insertPageMarkers, $restored->insertPageMarkers);
        $this->assertSame($original->markerFormat, $restored->markerFormat);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        PageConfig::fromJson('{ invalid }');
    }

    #[Test]
    public function it_enforces_readonly_on_extract_pages_property(): void
    {
        $this->expectException(\Error::class);

        $config = new PageConfig(extractPages: true);
        $config->extractPages = false;
    }

    #[Test]
    public function it_enforces_readonly_on_marker_format_property(): void
    {
        $this->expectException(\Error::class);

        $config = new PageConfig(markerFormat: 'Page {page_num}');
        $config->markerFormat = 'Chapter {page_num}';
    }

    #[Test]
    public function it_creates_from_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'page_');
        if ($tempFile === false) {
            $this->markTestSkipped('Unable to create temporary file');
        }

        try {
            file_put_contents($tempFile, json_encode([
                'extract_pages' => true,
                'insert_page_markers' => true,
            ]));

            $config = PageConfig::fromFile($tempFile);

            $this->assertTrue($config->extractPages);
            $this->assertTrue($config->insertPageMarkers);
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

        PageConfig::fromFile('/nonexistent/path/config.json');
    }

    #[Test]
    public function it_handles_type_coercion_for_extract_pages(): void
    {
        $data = ['extract_pages' => 1];
        $config = PageConfig::fromArray($data);

        $this->assertIsBool($config->extractPages);
        $this->assertTrue($config->extractPages);
    }

    #[Test]
    public function it_handles_type_coercion_for_marker_format(): void
    {
        $data = ['marker_format' => 123];
        $config = PageConfig::fromArray($data);

        $this->assertIsString($config->markerFormat);
        $this->assertSame('123', $config->markerFormat);
    }

    #[Test]
    public function it_supports_custom_marker_formats(): void
    {
        $formats = [
            '### Page {page_num} ###',
            'Page: {page_num}',
            '<<{page_num}>>',
            'SECTION_{page_num}',
        ];

        foreach ($formats as $format) {
            $config = new PageConfig(markerFormat: $format);
            $this->assertSame($format, $config->markerFormat);

            $array = $config->toArray();
            $this->assertSame($format, $array['marker_format']);
        }
    }

    #[Test]
    public function it_json_output_is_prettified(): void
    {
        $config = new PageConfig();
        $json = $config->toJson();

        $this->assertStringContainsString("\n", $json);
        $this->assertStringContainsString('  ', $json);
    }
}
