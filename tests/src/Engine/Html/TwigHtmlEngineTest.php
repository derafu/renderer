<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Engine\Html;

use Derafu\Renderer\Engine\Html\TwigHtmlEngine;
use Derafu\Renderer\Formatter\DataFormatter;
use Derafu\Renderer\Formatter\Extension\TwigFormatterExtension;
use Derafu\Renderer\Renderer;
use Derafu\Twig\Contract\TwigServiceInterface;
use Derafu\Twig\Service\TwigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The Twig environment the engine renders with can be reached, for tools that
 * have to work with the templates themselves (for example parsing them to
 * check what they refer to) using the same functions, tags and extensions that
 * render them.
 */
#[CoversClass(TwigHtmlEngine::class)]
#[UsesClass(Renderer::class)]
#[UsesClass(DataFormatter::class)]
#[UsesClass(TwigFormatterExtension::class)]
final class TwigHtmlEngineTest extends TestCase
{
    public function testItReturnsTheEnvironmentOfItsTwigService(): void
    {
        $environment = new Environment(new ArrayLoader());

        $service = $this->createStub(TwigServiceInterface::class);
        $service->method('getTwig')->willReturn($environment);

        $this->assertSame($environment, (new TwigHtmlEngine($service))->getTwig());
    }

    public function testTheEnvironmentOfARendererIsTheOneThatRendersItsTemplates(): void
    {
        $twig = new TwigService(['paths' => [__DIR__ . '/../../../fixtures']]);
        $renderer = new Renderer([new TwigHtmlEngine($twig)]);

        $engine = $renderer->getEngine('twig');
        $this->assertInstanceOf(TwigHtmlEngine::class, $engine);

        // It is the real, configured environment: it finds the templates of
        // the paths the renderer was created with.
        $this->assertTrue($engine->getTwig()->getLoader()->exists('custom_template.html.twig'));
    }

    private function engine(): TwigHtmlEngine
    {
        return new TwigHtmlEngine(new TwigService(new Environment(new ArrayLoader([
            'page.html.twig' => 'Hello {{ name }}, {{ options.mood }}.',
        ]))));
    }

    public function testATemplateIsRenderedWithTheDataAndTheOptions(): void
    {
        $html = $this->engine()->render('page', ['name' => 'World'], ['mood' => 'welcome']);

        $this->assertSame('Hello World, welcome.', $html);
    }

    public function testAStringIsRenderedWithTheDataAndTheOptions(): void
    {
        $html = $this->engine()->renderFromString('{{ name }} is {{ options.mood }}', ['name' => 'It'], ['mood' => 'fine']);

        $this->assertSame('It is fine', $html);
    }

    public function testItIsTheEngineOfTheTwigTemplates(): void
    {
        $engine = $this->engine();

        $this->assertSame('twig', $engine->getName());
        $this->assertSame(['html.twig', 'txt.twig'], $engine->getSupportedExtensions());
    }
}
