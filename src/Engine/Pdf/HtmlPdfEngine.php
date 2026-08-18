<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Renderer\Engine\Pdf;

use Derafu\Renderer\Contract\EngineInterface;
use Derafu\Renderer\Exception\ConfigurationException;
use Derafu\Twig\Contract\TwigServiceInterface;
use LogicException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Throwable;

/**
 * PDF engine implementation using mPDF.
 */
class HtmlPdfEngine implements EngineInterface
{
    /**
     * Strategy to use for PDF rendering.
     *
     * @var string
     */
    public const STRATEGY_MPDF = 'mpdf';

    /**
     * Strategy to use for PDF rendering.
     *
     * @var string
     */
    public const STRATEGY_CHROME = 'chrome';

    /**
     * Default strategy to use when no strategy is specified.
     *
     * @var string
     */
    private const DEFAULT_STRATEGY = self::STRATEGY_MPDF;

    /**
     * @param TwigServiceInterface $twigService
     * @param array<string,mixed> $options Engine options.
     *
     * Supported keys:
     *   - local_assets_path: base directory used to resolve root-relative
     *     image sources ("/img/foo.png") against the local filesystem
     *     instead of letting mPDF fetch them over HTTP. See
     *     resolveLocalPath().
     */
    public function __construct(
        private readonly TwigServiceInterface $twigService,
        private readonly array $options = []
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function render(
        string $template,
        array $data = [],
        array $options = []
    ): string {
        // Merge runtime options with template data.
        $context = array_replace_recursive(
            ['options' => $options],
            $data
        );

        // Render template to HTML using the Twig service.
        $html = $this->twigService->render($template, $context);

        // Write HTML to PDF.
        return $this->writeHtmlToPdf($html, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function renderFromString(
        string $content,
        array $data = [],
        array $options = []
    ): string {
        // Merge runtime options with template data.
        $context = array_replace_recursive(
            ['options' => $options],
            $data
        );

        // Render template to HTML using the Twig service.
        $html = $this->twigService->renderFromString($content, $context);

        // Write HTML to PDF.
        return $this->writeHtmlToPdf($html, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function getSupportedExtensions(): array
    {
        return ['pdf.twig'];
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'pdf';
    }

    /**
     * Writes HTML to PDF.
     *
     * @param string $html HTML content.
     * @param array<string,mixed> $options Runtime options.
     * @return string PDF content.
     */
    private function writeHtmlToPdf(
        string $html,
        array $options
    ): string {
        // Create PDF from HTML using the selected strategy.
        $strategy = $options['strategy'] ?? self::DEFAULT_STRATEGY;
        $pdfConfig = $options['config']['pdf'] ?? [];

        // Use mPDF strategy.
        if ($strategy === self::STRATEGY_MPDF) {
            return $this->writeHtmlToPdfWithMpdf($html, $pdfConfig);
        }

        // Use Chrome strategy.
        if ($strategy === self::STRATEGY_CHROME) {
            return $this->writeHtmlToPdfWithChrome($html, $pdfConfig);
        }

        // The selected strategy is not supported.
        throw new LogicException(sprintf(
            'Unsupported strategy for PDF rendering: %s',
            $strategy
        ));
    }

    /**
     * Writes HTML to PDF using mPDF.
     *
     * @param string $html HTML content.
     * @param array<string,mixed> $options Runtime options.
     * @return string PDF content.
     */
    private function writeHtmlToPdfWithMpdf(
        string $html,
        array $options
    ): string {
        // A per-call override takes precedence over the one configured once
        // when this engine was built; resolveLocalPath() falls back to
        // auto-detecting it (DOCUMENT_ROOT) if neither is set.
        $localAssetsPath = $options['local_assets_path']
            ?? $this->options['local_assets_path']
            ?? null;
        unset($options['local_assets_path']);

        $html = $this->rewriteLocalImageSources($html, $localAssetsPath);

        $pdf = $this->getMpdfInstance($options);
        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Rewrites root-relative <img> sources ("/img/foo.png") to a real local
     * filesystem path when one can be resolved, so mPDF reads the file
     * directly from disk instead of trying to fetch it over HTTP.
     *
     * Without this, mPDF resolves a root-relative image against the current
     * request's own host (there is no other reference point available),
     * turning it into a URL that points back at the very server rendering
     * the PDF. Fetching that URL blocks until the request's own execution
     * time limit kills the process — on a single-worker server (e.g. PHP's
     * built-in dev server) that takes the whole server down; on a
     * multi-worker one it still ties up a worker for no reason, since the
     * file is already on the same local disk.
     *
     * Sources that already point to a real file (an absolute filesystem
     * path unrelated to this site, or one already resolved) are left
     * untouched, as are protocol-relative ("//cdn...") and scheme-based
     * ("http://...") sources, which are not this package's concern.
     *
     * @param string $html HTML content.
     * @param string|null $localAssetsPathOverride Explicit base directory,
     * if one was configured (call-level or engine-level).
     * @return string HTML with resolvable image sources rewritten.
     */
    private function rewriteLocalImageSources(
        string $html,
        ?string $localAssetsPathOverride
    ): string {
        return preg_replace_callback(
            '/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/i',
            function (array $matches) use ($localAssetsPathOverride) {
                $src = $matches[2];

                if (is_file($src)) {
                    return $matches[0];
                }

                if (!str_starts_with($src, '/') || str_starts_with($src, '//')) {
                    return $matches[0];
                }

                $resolved = $this->resolveLocalPath($src, $localAssetsPathOverride);

                return $resolved === null
                    ? $matches[0]
                    : str_replace($src, $resolved, $matches[0]);
            },
            $html
        ) ?? $html;
    }

    /**
     * Resolves a root-relative path against a short list of candidate base
     * directories, in order: an explicit override, the request's own
     * document root, that document root's "static" subdirectory (the
     * convention used by Derafu\Http\Middleware\StaticFilesMiddleware), and
     * — since some deployments (e.g. a Deployer-style "current" release
     * symlink fronted by Caddy's `root * .../current` with a
     * `try_files public/index.php` rule) report a DOCUMENT_ROOT that is
     * itself one level above the real public/ directory — that same
     * document root with "/public" and "/public/static" appended. Returns
     * the first candidate that is a real, readable file, or null if none
     * of them are.
     *
     * @param string $src Root-relative source, e.g. "/img/foo.png".
     * @param string|null $override Explicit base directory, if any.
     * @return string|null
     */
    private function resolveLocalPath(string $src, ?string $override): ?string
    {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;

        // array_filter() drops null (and empty-string) entries, so every
        // $base reaching the loop below is guaranteed to be a non-empty
        // string — no need to check for null inside the loop.
        $candidates = array_filter([
            $override,
            $documentRoot,
            $documentRoot !== null ? $documentRoot . '/static' : null,
            $documentRoot !== null ? $documentRoot . '/public' : null,
            $documentRoot !== null ? $documentRoot . '/public/static' : null,
        ]);

        foreach ($candidates as $base) {
            $candidate = rtrim($base, '/') . $src;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Gets or creates the PDF instance.
     *
     * Create new instance for each render to avoid state issues.
     *
     * @param array<string,mixed> $options Runtime options.
     * @return Mpdf
     */
    private function getMpdfInstance(array $options = []): Mpdf
    {
        if (empty($options['tempDir'])) {
            $options['tempDir'] = sys_get_temp_dir();
        }

        try {
            return new Mpdf($options);
        } catch (Throwable $e) {
            throw ConfigurationException::forInvalidOption(
                'pdf',
                json_encode($options),
                $e->getMessage()
            );
        }
    }

    /**
     * Writes HTML to PDF using Chrome.
     *
     * @param string $html HTML content.
     * @param array<string,mixed> $options Runtime options.
     * @return string PDF content.
     */
    private function writeHtmlToPdfWithChrome(
        string $html,
        array $options
    ): string {
        throw new LogicException(
            'Writing HTML to PDF using Chrome is not yet supported.'
        );
    }
}
