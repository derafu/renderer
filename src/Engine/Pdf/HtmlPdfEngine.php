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
     */
    public function __construct(
        private readonly TwigServiceInterface $twigService
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
        $pdf = $this->getMpdfInstance($options);
        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
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
