<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Document and Template Rendering Library.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Factory;

use Derafu\Renderer\Contract\EngineInterface;
use Derafu\Renderer\Engine\Html\MarkdownHtmlEngine;
use Derafu\Renderer\Engine\Html\PhpHtmlEngine;
use Derafu\Renderer\Engine\Html\TwigHtmlEngine;
use Derafu\Renderer\Engine\Pdf\HtmlPdfEngine;
use Derafu\Renderer\Exception\EngineException;
use Derafu\Renderer\Factory\RendererFactory;
use Derafu\Renderer\Formatter\DataFormatter;
use Derafu\Renderer\Formatter\Extension\TwigFormatterExtension;
use Derafu\Renderer\Formatter\Handler\GenericFormatterHandler;
use Derafu\Renderer\Renderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * A renderer with the engines that are asked for, always with the Twig one.
 */
#[CoversClass(RendererFactory::class)]
#[UsesClass(Renderer::class)]
#[UsesClass(TwigHtmlEngine::class)]
#[UsesClass(MarkdownHtmlEngine::class)]
#[UsesClass(PhpHtmlEngine::class)]
#[UsesClass(HtmlPdfEngine::class)]
#[UsesClass(DataFormatter::class)]
#[UsesClass(GenericFormatterHandler::class)]
#[UsesClass(TwigFormatterExtension::class)]
#[UsesClass(EngineException::class)]
final class RendererFactoryTest extends TestCase
{
    public function testByDefaultItHasTheTwigEngine(): void
    {
        $renderer = RendererFactory::create();

        $this->assertInstanceOf(TwigHtmlEngine::class, $renderer->getEngine('twig'));
        $this->assertEngineIsMissing($renderer, 'markdown');
    }

    public function testItHasTheEnginesThatAreAskedFor(): void
    {
        $renderer = RendererFactory::create(['engines' => ['twig', 'markdown', 'php', 'pdf']]);

        $this->assertInstanceOf(TwigHtmlEngine::class, $renderer->getEngine('twig'));
        $this->assertInstanceOf(MarkdownHtmlEngine::class, $renderer->getEngine('markdown'));
        $this->assertInstanceOf(PhpHtmlEngine::class, $renderer->getEngine('php'));
        $this->assertInstanceOf(HtmlPdfEngine::class, $renderer->getEngine('pdf'));
    }

    public function testTheTwigEngineIsThereEvenIfItWasNotAskedFor(): void
    {
        $renderer = RendererFactory::create(['engines' => ['markdown']]);

        $this->assertInstanceOf(TwigHtmlEngine::class, $renderer->getEngine('twig'));
        $this->assertInstanceOf(MarkdownHtmlEngine::class, $renderer->getEngine('markdown'));
    }

    public function testAnEngineThatDoesNotExistIsSkipped(): void
    {
        $renderer = RendererFactory::create(['engines' => ['twig', 'unknown']]);

        $this->assertEngineIsMissing($renderer, 'unknown');
    }

    public function testAnEngineThatIsGivenIsUsedWithItsName(): void
    {
        $engine = $this->createStub(EngineInterface::class);
        $engine->method('getName')->willReturn('custom');
        $engine->method('getSupportedExtensions')->willReturn([]);

        $renderer = RendererFactory::create(['engines' => [$engine]]);

        $this->assertSame($engine, $renderer->getEngine('custom'));
        $this->assertInstanceOf(TwigHtmlEngine::class, $renderer->getEngine('twig'));
    }

    public function testTheFormattersOfTheOptionsAreInTheTemplates(): void
    {
        $renderer = RendererFactory::create([
            'formatters' => ['upper' => fn (string $value): string => strtoupper($value)],
        ]);

        $this->assertSame('HELLO', $renderer->renderFromString("{{ 'hello'|format_as('upper') }}"));
    }

    public function testTheTwigServiceCanBeCreatedWithoutAFormatterAndItGetsOneOfTheFormatters(): void
    {
        $service = RendererFactory::createTwigService([
            'formatters' => ['upper' => fn (string $value): string => strtoupper($value)],
            'extra' => false,
        ]);
        $data = [];

        $this->assertSame('HELLO', $service->renderFromString("{{ 'hello'|format_as('upper') }}", $data));
    }

    public function testTheTwigServiceUsesTheFormatterThatIsGiven(): void
    {
        $service = RendererFactory::createTwigService([
            'formatter' => new DataFormatter(['bracket' => '[%s]']),
            'extra' => false,
        ]);
        $data = [];

        $this->assertSame('[x]', $service->renderFromString("{{ 'x'|format_as('bracket') }}", $data));
    }

    private function assertEngineIsMissing(Renderer|\Derafu\Renderer\Contract\RendererInterface $renderer, string $name): void
    {
        $exception = null;
        try {
            $renderer->getEngine($name);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(EngineException::class, $exception);
    }
}
