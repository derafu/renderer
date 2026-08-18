<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Engine\Pdf;

use Derafu\Renderer\Engine\Html\TwigHtmlEngine;
use Derafu\Renderer\Engine\Pdf\HtmlPdfEngine;
use Derafu\Renderer\Factory\RendererFactory;
use Derafu\Renderer\Formatter\DataFormatter;
use Derafu\Renderer\Formatter\Extension\TwigFormatterExtension;
use Derafu\Renderer\Renderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Exercises the root-relative image resolution added to HtmlPdfEngine to
 * fix a real bug: a root-relative <img src="/..."> made mPDF build a URL
 * back to the very server rendering the PDF, which on a single-worker
 * server (PHP's built-in dev server) hangs until the request's execution
 * time limit kills the whole process. No mocks: real mPDF, real files on
 * disk, real timing assertions.
 */
#[CoversClass(HtmlPdfEngine::class)]
#[UsesClass(RendererFactory::class)]
#[UsesClass(Renderer::class)]
#[UsesClass(TwigHtmlEngine::class)]
#[UsesClass(DataFormatter::class)]
#[UsesClass(TwigFormatterExtension::class)]
final class HtmlPdfEngineLocalAssetsTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/../../../fixtures';

    private ?string $originalDocumentRoot;

    protected function setUp(): void
    {
        $this->originalDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
    }

    protected function tearDown(): void
    {
        // $_SERVER is a superglobal: restore it so this test never leaks
        // state into any other test running in the same process.
        if ($this->originalDocumentRoot === null) {
            unset($_SERVER['DOCUMENT_ROOT']);
        } else {
            $_SERVER['DOCUMENT_ROOT'] = $this->originalDocumentRoot;
        }
    }

    public function testRootRelativeImageResolvesViaAnExplicitEngineLevelOverrideAndRendersQuickly(): void
    {
        // Deliberately not touching $_SERVER['DOCUMENT_ROOT'] here: the
        // override must win regardless of whatever it is set to.

        $renderer = RendererFactory::create([
            'engines' => ['twig', 'pdf'],
            'paths' => [self::FIXTURES_DIR],
            'pdf' => ['local_assets_path' => self::FIXTURES_DIR . '/assets'],
        ]);

        $start = hrtime(true);
        $pdf = $renderer->render(
            self::FIXTURES_DIR . '/image_template.pdf.twig',
            ['title' => 'Con override', 'img_src' => '/img/logo.png']
        );
        $elapsedSeconds = (hrtime(true) - $start) / 1e9;

        $this->assertLessThan(
            5,
            $elapsedSeconds,
            'Rendering took long enough to suspect it tried to fetch the image over HTTP instead of reading it from disk.'
        );

        $this->assertImageWasEmbedded($pdf);

        $unresolved = $renderer->render(
            self::FIXTURES_DIR . '/image_template.pdf.twig',
            ['title' => 'Sin resolver', 'img_src' => '/img/no-existe-en-ningun-lado.png']
        );
        $this->assertImageWasNotEmbedded($unresolved);
    }

    public function testRootRelativeImageResolvesViaDocumentRootAutoDetectionWhenNoOverrideIsConfigured(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = self::FIXTURES_DIR . '/assets';

        $renderer = RendererFactory::create([
            'engines' => ['twig', 'pdf'],
            'paths' => [self::FIXTURES_DIR],
            // No 'pdf' options at all: this must work from DOCUMENT_ROOT alone.
        ]);

        $start = hrtime(true);
        $pdf = $renderer->render(
            self::FIXTURES_DIR . '/image_template.pdf.twig',
            ['title' => 'Auto-detectado', 'img_src' => '/img/logo.png']
        );
        $elapsedSeconds = (hrtime(true) - $start) / 1e9;

        $this->assertLessThan(5, $elapsedSeconds);
        $this->assertImageWasEmbedded($pdf);
    }

    /**
     * The exact case raised during design: a static HTML fragment with no
     * relation to derafu-content, whose <img> already points to a real,
     * absolute filesystem path. It must keep working exactly as it did
     * before this fix — no override configured, and it must not matter
     * what $_SERVER['DOCUMENT_ROOT'] happens to be, since is_file() on the
     * source as given short-circuits before either is even considered.
     */
    public function testAlreadyAbsoluteLocalFilesystemPathIsLeftUntouched(): void
    {
        $renderer = RendererFactory::create([
            'engines' => ['twig', 'pdf'],
            'paths' => [self::FIXTURES_DIR],
        ]);

        $realAbsolutePath = self::FIXTURES_DIR . '/assets/img/logo.png';

        $start = hrtime(true);
        $pdf = $renderer->render(
            self::FIXTURES_DIR . '/image_template.pdf.twig',
            ['title' => 'Path absoluto real', 'img_src' => $realAbsolutePath]
        );
        $elapsedSeconds = (hrtime(true) - $start) / 1e9;

        $this->assertLessThan(5, $elapsedSeconds);
        $this->assertImageWasEmbedded($pdf);
    }

    public function testResolveLocalPathPrefersTheOverrideThenDocumentRootThenDocumentRootStatic(): void
    {
        $engine = new HtmlPdfEngine(RendererFactory::createTwigService());
        $resolve = new ReflectionMethod($engine, 'resolveLocalPath');

        $_SERVER['DOCUMENT_ROOT'] = self::FIXTURES_DIR . '/assets';

        // Only resolvable through the override.
        $this->assertSame(
            self::FIXTURES_DIR . '/assets/img/logo.png',
            $resolve->invoke($engine, '/img/logo.png', self::FIXTURES_DIR . '/assets')
        );

        // No override: falls back to DOCUMENT_ROOT.
        $this->assertSame(
            self::FIXTURES_DIR . '/assets/img/logo.png',
            $resolve->invoke($engine, '/img/logo.png', null)
        );

        // Neither the override nor DOCUMENT_ROOT itself has it, but
        // DOCUMENT_ROOT . '/static' does (the StaticFilesMiddleware
        // convention).
        $_SERVER['DOCUMENT_ROOT'] = self::FIXTURES_DIR;
        $this->assertSame(
            self::FIXTURES_DIR . '/static/img/logo.png',
            $resolve->invoke($engine, '/img/logo.png', null)
        );

        // Nothing resolves it anywhere: null.
        $this->assertNull(
            $resolve->invoke($engine, '/img/no-existe-en-ningun-lado.png', null)
        );
    }

    public function testProtocolRelativeAndSchemeSourcesAreNeverRewritten(): void
    {
        $engine = new HtmlPdfEngine(RendererFactory::createTwigService(), [
            'local_assets_path' => self::FIXTURES_DIR . '/assets',
        ]);
        $rewrite = new ReflectionMethod($engine, 'rewriteLocalImageSources');

        $protocolRelative = '<img src="//cdn.example.com/img/logo.png">';
        $this->assertSame(
            $protocolRelative,
            $rewrite->invoke($engine, $protocolRelative, self::FIXTURES_DIR . '/assets')
        );

        $withScheme = '<img src="https://example.com/img/logo.png">';
        $this->assertSame(
            $withScheme,
            $rewrite->invoke($engine, $withScheme, self::FIXTURES_DIR . '/assets')
        );
    }

    /**
     * "/Subtype /Image" alone is not a reliable signal that our fixture PNG
     * was embedded: mPDF emits it internally regardless (e.g. for font
     * glyphs), even when the image never resolves. The fixture is a real
     * 1x1 pixel PNG, so "/Width 1" immediately followed by "/Height 1"
     * inside an actual Image XObject is specific enough to it.
     */
    private function assertImageWasEmbedded(string $pdf): void
    {
        $this->assertMatchesRegularExpression(
            '/\/Subtype\s*\/Image\s*\/Width\s+1\s*\/Height\s+1\b/',
            $pdf,
            'Expected the 1x1 fixture image to be embedded as a real Image XObject.'
        );
    }

    private function assertImageWasNotEmbedded(string $pdf): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/\/Subtype\s*\/Image\s*\/Width\s+1\s*\/Height\s+1\b/',
            $pdf,
            'Did not expect the 1x1 fixture image to be embedded.'
        );
    }
}
