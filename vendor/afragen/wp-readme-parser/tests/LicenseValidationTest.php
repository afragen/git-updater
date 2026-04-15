<?php

declare(strict_types=1);

namespace Fragen\WP_Readme_Parser\Tests;

use Fragen\WP_Readme_Parser\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for license header parsing and the validateLicense() method.
 *
 * @covers \Fragen\WP_Readme_Parser\Parser
 */
class LicenseValidationTest extends ParserTestCase
{
    // -------------------------------------------------------------------------
    // validateLicense() unit tests
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('compatibleLicenseProvider')]
    public function it_identifies_compatible_licenses(string $license): void
    {
        $parser = new Parser();
        $this->assertTrue($parser->validateLicense($license));
    }

    public static function compatibleLicenseProvider(): array
    {
        return [
            'GPLv2'              => ['GPLv2'],
            'GPL v2'             => ['GPL v2'],
            'GPLv2 or later'     => ['GPLv2 or later'],
            'GPLv3'              => ['GPLv3'],
            'GPL-2.0-or-later'   => ['GPL-2.0-or-later'],
            'MIT'                => ['MIT'],
            'Apache 2.0'         => ['Apache 2.0'],
            'Apache License 2.0' => ['Apache License 2.0'],
            'CC0'                => ['CC0'],
            'CC BY 4.0'          => ['CC BY 4.0'],
            'Unlicense'          => ['Unlicense'],
            'Public Domain'      => ['Public Domain'],
            'MPL-2.0'            => ['MPL-2.0'],
            'ISC'                => ['ISC'],
            'BSD 3-Clause'       => ['BSD 3-Clause'],
            'Simplified BSD'     => ['Simplified BSD'],
            'WTFPL'              => ['WTFPL'],
            'zlib'               => ['zlib'],
        ];
    }

    #[Test]
    #[DataProvider('incompatibleLicenseProvider')]
    public function it_identifies_incompatible_licenses(string $license): void
    {
        $parser = new Parser();
        $this->assertSame('invalid_license', $parser->validateLicense($license));
    }

    public static function incompatibleLicenseProvider(): array
    {
        return [
            'CC BY-NC'      => ['CC BY-NC 4.0'],
            'NonCommercial' => ['Creative Commons NonCommercial'],
            'CC BY-ND'      => ['CC BY-ND 4.0'],
            'Proprietary'   => ['Proprietary'],
            'you may not'   => ['You may not redistribute'],
            'Personal use'  => ['Personal use only'],
            'Apache 1'      => ['Apache 1.0'],
        ];
    }

    #[Test]
    #[DataProvider('unknownLicenseProvider')]
    public function it_returns_unknown_for_unrecognised_licenses(string $license): void
    {
        $parser = new Parser();
        $this->assertSame('unknown_license', $parser->validateLicense($license));
    }

    public static function unknownLicenseProvider(): array
    {
        return [
            'custom name'  => ['ACME Custom License 1.0'],
            'made up'      => ['SuperLicense'],
            'empty string' => ['   '],
        ];
    }

    // -------------------------------------------------------------------------
    // License header parsing
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_license_header(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertSame('GPLv2 or later', $parser->license);
    }

    #[Test]
    public function it_parses_explicit_license_uri_header(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertSame('https://www.gnu.org/licenses/gpl-2.0.html', $parser->license_uri);
    }

    #[Test]
    public function it_extracts_license_uri_embedded_in_license_field(): void
    {
        $readme = $this->makeReadme([
            'License' => 'GPLv2 - https://www.gnu.org/licenses/gpl-2.0.html',
        ]);
        $parser = $this->parse($readme);

        $this->assertStringContainsString('GPLv2', $parser->license);
        $this->assertSame('https://www.gnu.org/licenses/gpl-2.0.html', $parser->license_uri);
    }

    #[Test]
    public function it_does_not_overwrite_explicit_license_uri_with_embedded_one(): void
    {
        $readme = $this->makeReadme([
            'License'     => 'GPLv2 - https://embedded.example.com/',
            'License URI' => 'https://explicit.example.com/',
        ]);
        $parser = $this->parse($readme);

        // The explicit header wins.
        $this->assertSame('https://explicit.example.com/', $parser->license_uri);
    }

    // -------------------------------------------------------------------------
    // License warnings
    // -------------------------------------------------------------------------

    #[Test]
    public function it_warns_on_missing_license(): void
    {
        $parser = $this->parse("=== P ===\nRequires at least: 6.0\n\nDesc.");
        $this->assertArrayHasKey('license_missing', $parser->warnings);
    }

    #[Test]
    public function it_warns_on_incompatible_license(): void
    {
        $parser = $this->parseFixture('edge-cases/too-many-tags.txt');
        $this->assertArrayHasKey('invalid_license', $parser->warnings);
    }

    #[Test]
    public function it_warns_on_unknown_license(): void
    {
        $parser = $this->parse($this->makeReadme(['License' => 'ACME Custom License']));
        $this->assertArrayHasKey('unknown_license', $parser->warnings);
    }

    #[Test]
    public function it_does_not_warn_on_valid_license(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayNotHasKey('license_missing', $parser->warnings);
        $this->assertArrayNotHasKey('invalid_license', $parser->warnings);
        $this->assertArrayNotHasKey('unknown_license', $parser->warnings);
    }
}
