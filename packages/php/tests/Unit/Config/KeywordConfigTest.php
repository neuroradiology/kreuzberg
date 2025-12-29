<?php

declare(strict_types=1);

namespace Kreuzberg\Tests\Unit\Config;

use Kreuzberg\Config\KeywordConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for KeywordConfig readonly class.
 *
 * Tests construction, serialization, factory methods, readonly enforcement,
 * and handling of integer, float, and string properties.
 *
 * Test Coverage:
 * - Construction with default values
 * - Construction with custom values
 * - toArray() serialization with optional field inclusion
 * - fromArray() factory method
 * - fromJson() factory method
 * - toJson() serialization
 * - Readonly enforcement
 * - Float handling
 * - Null handling
 * - Invalid JSON handling
 * - Round-trip serialization
 */
#[CoversClass(KeywordConfig::class)]
#[Group('unit')]
#[Group('config')]
final class KeywordConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_default_values(): void
    {
        $config = new KeywordConfig();

        $this->assertSame(10, $config->maxKeywords);
        $this->assertSame(0.0, $config->minScore);
        $this->assertSame('en', $config->language);
    }

    #[Test]
    public function it_creates_with_custom_values(): void
    {
        $config = new KeywordConfig(
            maxKeywords: 20,
            minScore: 0.75,
            language: 'de',
        );

        $this->assertSame(20, $config->maxKeywords);
        $this->assertSame(0.75, $config->minScore);
        $this->assertSame('de', $config->language);
    }

    #[Test]
    public function it_serializes_to_array_with_only_non_default_values(): void
    {
        $config = new KeywordConfig(maxKeywords: 5);
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('max_keywords', $array);
        $this->assertSame(5, $array['max_keywords']);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $config = KeywordConfig::fromArray([]);

        $this->assertSame(10, $config->maxKeywords);
        $this->assertSame(0.0, $config->minScore);
        $this->assertSame('en', $config->language);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'max_keywords' => 15,
            'min_score' => 0.5,
            'language' => 'fr',
        ];
        $config = KeywordConfig::fromArray($data);

        $this->assertSame(15, $config->maxKeywords);
        $this->assertSame(0.5, $config->minScore);
        $this->assertSame('fr', $config->language);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $config = new KeywordConfig(
            maxKeywords: 25,
            minScore: 0.8,
            language: 'es',
        );
        $json = $config->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertSame(25, $decoded['max_keywords']);
        $this->assertSame(0.8, $decoded['min_score']);
        $this->assertSame('es', $decoded['language']);
    }

    #[Test]
    public function it_creates_from_json(): void
    {
        $json = json_encode([
            'max_keywords' => 30,
            'min_score' => 0.6,
            'language' => 'it',
        ]);
        $config = KeywordConfig::fromJson($json);

        $this->assertSame(30, $config->maxKeywords);
        $this->assertSame(0.6, $config->minScore);
        $this->assertSame('it', $config->language);
    }

    #[Test]
    public function it_round_trips_through_json(): void
    {
        $original = new KeywordConfig(
            maxKeywords: 50,
            minScore: 0.9,
            language: 'ja',
        );

        $json = $original->toJson();
        $restored = KeywordConfig::fromJson($json);

        $this->assertSame($original->maxKeywords, $restored->maxKeywords);
        $this->assertSame($original->minScore, $restored->minScore);
        $this->assertSame($original->language, $restored->language);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        KeywordConfig::fromJson('{ invalid }');
    }

    #[Test]
    public function it_enforces_readonly_on_max_keywords_property(): void
    {
        $this->expectException(\Error::class);

        $config = new KeywordConfig(maxKeywords: 10);
        $config->maxKeywords = 20;
    }

    #[Test]
    public function it_enforces_readonly_on_min_score_property(): void
    {
        $this->expectException(\Error::class);

        $config = new KeywordConfig(minScore: 0.5);
        $config->minScore = 0.7;
    }

    #[Test]
    public function it_enforces_readonly_on_language_property(): void
    {
        $this->expectException(\Error::class);

        $config = new KeywordConfig(language: 'en');
        $config->language = 'de';
    }

    #[Test]
    public function it_creates_from_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'kw_');
        if ($tempFile === false) {
            $this->markTestSkipped('Unable to create temporary file');
        }

        try {
            file_put_contents($tempFile, json_encode([
                'max_keywords' => 15,
                'min_score' => 0.5,
            ]));

            $config = KeywordConfig::fromFile($tempFile);

            $this->assertSame(15, $config->maxKeywords);
            $this->assertSame(0.5, $config->minScore);
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

        KeywordConfig::fromFile('/nonexistent/path/config.json');
    }

    #[Test]
    public function it_handles_type_coercion_for_max_keywords(): void
    {
        $data = ['max_keywords' => '25'];
        $config = KeywordConfig::fromArray($data);

        $this->assertIsInt($config->maxKeywords);
        $this->assertSame(25, $config->maxKeywords);
    }

    #[Test]
    public function it_handles_type_coercion_for_min_score(): void
    {
        $data = ['min_score' => '0.75'];
        $config = KeywordConfig::fromArray($data);

        $this->assertIsFloat($config->minScore);
        $this->assertSame(0.75, $config->minScore);
    }

    #[Test]
    public function it_handles_integer_min_score_coercion(): void
    {
        $data = ['min_score' => 1];
        $config = KeywordConfig::fromArray($data);

        $this->assertIsFloat($config->minScore);
        $this->assertSame(1.0, $config->minScore);
    }

    #[Test]
    public function it_handles_type_coercion_for_language(): void
    {
        $data = ['language' => 123];
        $config = KeywordConfig::fromArray($data);

        $this->assertIsString($config->language);
        $this->assertSame('123', $config->language);
    }

    #[Test]
    public function it_supports_zero_min_score(): void
    {
        $config = new KeywordConfig(minScore: 0.0);

        $this->assertSame(0.0, $config->minScore);
        $array = $config->toArray();
        $this->assertArrayHasKey('min_score', $array);
        $this->assertSame(0.0, $array['min_score']);
    }

    #[Test]
    public function it_supports_high_min_score(): void
    {
        $config = new KeywordConfig(minScore: 1.0);

        $this->assertSame(1.0, $config->minScore);
        $array = $config->toArray();
        $this->assertSame(1.0, $array['min_score']);
    }
}
