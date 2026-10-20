<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Document and Template Rendering Library.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer;

use Derafu\Renderer\Contract\EngineInterface;
use Derafu\Renderer\Exception\ConfigurationException;
use Derafu\Renderer\Exception\EngineException;
use Derafu\Renderer\Renderer;
use Derafu\Translation\Contract\TranslatableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The engine and the format that the options ask for must be names.
 */
#[CoversClass(Renderer::class)]
#[UsesClass(ConfigurationException::class)]
#[UsesClass(EngineException::class)]
final class RendererTest extends TestCase
{
    /**
     * @return array<string, array{array<string,mixed>, string}>
     */
    public static function provideOptionsThatAreNotNames(): array
    {
        return [
            'an engine that is an array' => [
                ['engine' => ['twig']],
                'Invalid value "array" for configuration option "engine". Expected: string.',
            ],
            'a format that is a number' => [
                ['format' => 7],
                'Invalid value "int" for configuration option "format". Expected: string.',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $options
     */
    #[DataProvider('provideOptionsThatAreNotNames')]
    public function testAnOptionThatIsNotAStringIsATranslatableError(array $options, string $message): void
    {
        $renderer = new Renderer(['twig' => $this->createStub(EngineInterface::class)]);

        $exception = null;
        try {
            $renderer->render('page.html.twig', [], $options);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * An engine that answers with its name and the way it was called.
     *
     * @param list<string> $extensions
     */
    private function engine(string $name, array $extensions = []): EngineInterface
    {
        $engine = $this->createStub(EngineInterface::class);
        $engine->method('getName')->willReturn($name);
        $engine->method('getSupportedExtensions')->willReturn($extensions);
        $engine->method('render')->willReturnCallback(
            fn (string $template, array $data, array $options): string => $name . ' renders ' . $template
        );
        $engine->method('renderFromString')->willReturnCallback(
            fn (string $content, array $data, array $options): string => $name . ' renders from string ' . $content
        );

        return $engine;
    }

    public function testTheEngineOfTheExtensionOfTheTemplateRendersIt(): void
    {
        $renderer = new Renderer([
            'twig' => $this->engine('twig', ['html.twig']),
            'markdown' => $this->engine('markdown', ['md', 'markdown']),
        ]);

        $this->assertSame('markdown renders page.md', $renderer->render('page.md'));
        $this->assertSame('markdown renders page.markdown', $renderer->render('page.markdown'));
        $this->assertSame('twig renders page.html.twig', $renderer->render('page.html.twig'));
    }

    public function testWithoutAnEngineThatMatchesTheDefaultOneRenders(): void
    {
        $renderer = new Renderer(['twig' => $this->engine('twig'), 'php' => $this->engine('php')], 'php');

        $this->assertSame('php renders page.unknown', $renderer->render('page.unknown'));
    }

    public function testAnEngineThatIsAddedAfterwardsAlsoGetsItsExtensions(): void
    {
        $renderer = new Renderer(['twig' => $this->engine('twig')]);

        $this->assertSame($renderer, $renderer->addEngine('php', $this->engine('php', ['php'])));
        $this->assertSame('php renders page.php', $renderer->render('page.php'));
    }

    public function testTheEngineOfTheOptionsIsTheOneThatRenders(): void
    {
        $renderer = new Renderer([
            'twig' => $this->engine('twig', ['html.twig']),
            'pdf' => $this->engine('pdf', ['pdf.twig']),
        ]);

        $this->assertSame('pdf renders page.html.twig', $renderer->render('page.html.twig', [], ['engine' => 'pdf']));
    }

    public function testTheFormatOfTheOptionsIsTranslatedToItsEngine(): void
    {
        $renderer = new Renderer([
            'twig' => $this->engine('twig'),
            'pdf' => $this->engine('pdf'),
            'markdown' => $this->engine('markdown'),
        ], 'markdown');

        $this->assertSame('twig renders page', $renderer->render('page', [], ['format' => 'html']));
        $this->assertSame('pdf renders page', $renderer->render('page', [], ['format' => 'pdf']));
        $this->assertSame('markdown renders page', $renderer->render('page', [], ['format' => 'markdown']));
    }

    public function testAContentCanBeRenderedFromAString(): void
    {
        $renderer = new Renderer(['twig' => $this->engine('twig'), 'pdf' => $this->engine('pdf')]);

        $this->assertSame('twig renders from string Hello', $renderer->renderFromString('Hello'));
        $this->assertSame('pdf renders from string Hello', $renderer->renderFromString('Hello', [], ['engine' => 'pdf']));
    }

    public function testAnEngineThatDoesNotExistIsATranslatableError(): void
    {
        $renderer = new Renderer(['twig' => $this->engine('twig')]);

        $exception = null;
        try {
            $renderer->getEngine('missing');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(EngineException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('Rendering engine "missing" not found.', $exception->getMessage());
    }

    public function testTheEnginesThatItHasAreTheOnesThatWereGiven(): void
    {
        $twig = $this->engine('twig');

        $this->assertSame($twig, (new Renderer(['twig' => $twig]))->getEngine('twig'));
    }
}
