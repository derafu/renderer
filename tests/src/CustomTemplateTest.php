<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer;

use Derafu\Markdown\Service\MarkdownService;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Renderer\Engine\Html\MarkdownHtmlEngine;
use Derafu\Renderer\Engine\Html\PhpHtmlEngine;
use Derafu\Renderer\Engine\Html\TwigHtmlEngine;
use Derafu\Renderer\Engine\Pdf\HtmlPdfEngine;
use Derafu\Renderer\Formatter\DataFormatter;
use Derafu\Renderer\Formatter\Extension\TwigFormatterExtension;
use Derafu\Renderer\Formatter\Handler\GenericFormatterHandler;
use Derafu\Renderer\Renderer;
use Derafu\Twig\Service\TwigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Renderer::class)]
#[CoversClass(DataFormatter::class)]
#[CoversClass(GenericFormatterHandler::class)]
#[CoversClass(TwigFormatterExtension::class)]
#[CoversClass(MarkdownHtmlEngine::class)]
#[CoversClass(PhpHtmlEngine::class)]
#[CoversClass(HtmlPdfEngine::class)]
#[CoversClass(TwigHtmlEngine::class)]
class CustomTemplateTest extends TestCase
{
    private RendererInterface $renderer;

    /**
     * @var array{title: string, content: string, date: string}
     */
    private array $data;

    public function setUp(): void
    {
        $paths = [__DIR__ . '/../fixtures'];
        $formatter = new DataFormatter([
            'date' => function (string $date): string {
                $timestamp = strtotime($date);
                return date('d/m/Y', (int) $timestamp);
            },
        ]);
        $twig = new TwigService([
            'paths' => $paths,
            'extensions' => [new TwigFormatterExtension($formatter)],
        ]);
        $this->renderer = new Renderer([
            new TwigHtmlEngine($twig),
            new MarkdownHtmlEngine(new MarkdownService(paths: $paths), $twig),
            new PhpHtmlEngine($twig, $formatter, $paths),
            new HtmlPdfEngine($twig),
        ]);

        $this->data = [
            'title' => 'Derafu',
            'content' => 'I Love Derafu <3',
            'date' => date('Y-m-d'),
        ];
    }

    public function testRenderCustomTemplateHtmlTwig(): void
    {
        $template = __DIR__ . '/../fixtures/custom_template';
        $html = $this->renderer->render($template, $this->data);
        $this->assertStringContainsString('<h1>Derafu</h1>', $html);
        // Twig auto-escapes HTML output: "<3" becomes "&lt;3".
        $this->assertStringContainsString('<p>I Love Derafu &lt;3</p>', $html);
        $this->assertStringContainsString('Hoy es ' . date('d/m/Y') . '.', $html);
        $this->saveRendered($template, 'html', $html);
    }

    public function testRenderCustomTemplateHtmlTwigEnginePdf(): void
    {
        $template = __DIR__ . '/../fixtures/custom_template';
        $pdf = $this->renderer->render($template, $this->data, ['engine' => 'pdf']);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->saveRendered($template, 'pdf', $pdf);
    }

    public function testRenderCustomTemplatePdfTwig(): void
    {
        $template = __DIR__ . '/../fixtures/custom_template.pdf.twig';
        $pdf = $this->renderer->render($template, $this->data);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->saveRendered($template, 'pdf', $pdf);
    }

    public function testRenderCustomTemplateMarkdown(): void
    {
        $template = __DIR__ . '/../fixtures/custom_template.md';
        $html = $this->renderer->render($template, $this->data);
        // The Markdown engine adds a permalink anchor to the heading, so the
        // heading is its text and, maybe, that anchor.
        $this->assertMatchesRegularExpression('#<h1[^>]*>Derafu(?:<a\b.*?</a>)?</h1>#s', $html);
        $this->assertStringContainsString('<p>I Love Derafu &lt;3</p>', $html);
        // custom_template.md uses {{ date }} directly, with no formatter.
        $this->assertStringContainsString('Hoy es ' . $this->data['date'] . '.', $html);
        $this->saveRendered($template, 'html', $html);
    }

    public function testRenderCustomTemplatePhp(): void
    {
        $template = __DIR__ . '/../fixtures/custom_template.php';
        $html = $this->renderer->render($template, $this->data);
        $this->assertStringContainsString('<h1>Derafu</h1>', $html);
        // Unlike the Twig engine, plain PHP templates do not auto-escape.
        $this->assertStringContainsString('<p>I Love Derafu <3</p>', $html);
        $this->assertStringContainsString('Hoy es ' . date('d/m/Y') . '.', $html);
        $this->saveRendered($template, 'html', $html);
    }

    private function saveRendered(string $template, string $extension, string $content): void
    {
        $file = str_replace('/fixtures/', '/rendered/', $template) . '.' . $extension;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        file_put_contents($file, $content);
    }
}
