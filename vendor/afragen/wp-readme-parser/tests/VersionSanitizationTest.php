<?php

declare(strict_types=1);

namespace Fragen\WP_Readme_Parser\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for all version-header sanitization logic.
 *
 * @covers \Fragen\WP_Readme_Parser\Parser
 */
class VersionSanitizationTest extends ParserTestCase
{
    // -------------------------------------------------------------------------
    // Requires at least
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('requiresVersionProvider')]
    public function it_sanitizes_requires_version(string $input, string $expected): void
    {
        $parser = $this->parse($this->makeReadme(['Requires at least' => $input]));
        $this->assertSame($expected, $parser->requires);
    }

    public static function requiresVersionProvider(): array
    {
        return [
            'plain x.y'        => ['6.0',          '6.0'],
            'x.y.z patch'      => ['6.0.1',         '6.0.1'],
            'WP prefix'        => ['WP 6.0',        '6.0'],
            'WordPress prefix' => ['WordPress 6.0', '6.0'],
            'or higher suffix' => ['6.0 or higher', '6.0'],
            'and above suffix' => ['6.0 and above', '6.0'],
            'plus suffix'      => ['6.0+',          '6.0'],
            'beta stripped'    => ['6.0-beta1',     '6.0'],
            'RC stripped'      => ['6.0-RC2',       '6.0'],
            'invalid string'   => ['not-a-version', ''],
            'major only'       => ['6',             ''],   // must be x.y at minimum,
        ];
    }

    #[Test]
    public function it_sets_requires_header_ignored_warning_on_bad_value(): void
    {
        $parser = $this->parse($this->makeReadme(['Requires at least' => 'not-a-version']));
        $this->assertArrayHasKey('requires_header_ignored', $parser->warnings);
    }

    #[Test]
    public function it_does_not_warn_when_requires_is_valid(): void
    {
        $parser = $this->parse($this->makeReadme(['Requires at least' => '6.0']));
        $this->assertArrayNotHasKey('requires_header_ignored', $parser->warnings);
    }

    // -------------------------------------------------------------------------
    // Tested up to
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('validTestedVersionProvider')]
    public function it_sanitizes_tested_version(string $input, string $expected): void
    {
        $parser = $this->parse($this->makeReadme(['Tested up to' => $input]));
        $this->assertSame($expected, $parser->tested);
    }

    public static function validTestedVersionProvider(): array
    {
        return [
            'plain x.y'        => ['6.5',          '6.5'],
            'x.y.z'            => ['6.5.1',         '6.5.1'],
            'WP prefix'        => ['WP 6.5',        '6.5'],
            'WordPress prefix' => ['WordPress 6.5', '6.5'],
            'RC stripped'      => ['6.5-RC1',       '6.5'],
            'alpha stripped'   => ['6.5-alpha',     '6.5'],
            'invalid'          => ['banana',        ''],
            'major only'       => ['6',             ''],
        ];
    }

    #[Test]
    public function it_sets_tested_header_ignored_warning_on_bad_value(): void
    {
        $parser = $this->parse($this->makeReadme(['Tested up to' => 'nope']));
        $this->assertArrayHasKey('tested_header_ignored', $parser->warnings);
    }

    // -------------------------------------------------------------------------
    // Requires PHP
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('requiresPhpProvider')]
    public function it_sanitizes_requires_php(string $input, string $expected): void
    {
        $parser = $this->parse($this->makeReadme(['Requires PHP' => $input]));
        $this->assertSame($expected, $parser->requires_php);
    }

    public static function requiresPhpProvider(): array
    {
        return [
            'x.y'               => ['8.1',    '8.1'],
            'x.y.z'             => ['8.1.0',  '8.1.0'],
            'major.minor.patch' => ['7.4.33', '7.4.33'],
            'invalid wildcard'  => ['8.x',    ''],
            'text'              => ['8 or higher', ''],
            'major only'        => ['8',       ''],
        ];
    }

    #[Test]
    public function it_sets_requires_php_header_ignored_warning_on_bad_value(): void
    {
        $parser = $this->parse($this->makeReadme(['Requires PHP' => '8.x']));
        $this->assertArrayHasKey('requires_php_header_ignored', $parser->warnings);
    }

    // -------------------------------------------------------------------------
    // Stable tag
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('stableTagProvider')]
    public function it_sanitizes_stable_tag(string $input, string $expected): void
    {
        $parser = $this->parse($this->makeReadme(['Stable tag' => $input]));
        $this->assertSame($expected, $parser->stable_tag);
    }

    public static function stableTagProvider(): array
    {
        return [
            'plain semver'           => ['1.2.3',       '1.2.3'],
            'trunk'                  => ['trunk',        'trunk'],
            'quoted trunk'           => ['"trunk"',      'trunk'],
            'single-quoted trunk'    => ["'trunk'",      'trunk'],
            'tags/ prefix'           => ['tags/1.2.3',   '1.2.3'],
            'slash tags/ prefix'     => ['/tags/1.2.3',  '1.2.3'],
            'leading dot padded'     => ['.5',           '0.5'],
            'special chars stripped' => ['1.2!3',      '1.23'],  // ! is stripped,
        ];
    }
}
