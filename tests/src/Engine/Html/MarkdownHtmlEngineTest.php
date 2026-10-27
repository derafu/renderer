<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Document and Template Rendering Library.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Engine\Html;

use Derafu\Markdown\Service\MarkdownService;
use Derafu\Renderer\Engine\Html\MarkdownHtmlEngine;
use Derafu\Renderer\Exception\ConfigurationException;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Twig\Contract\TemplatePathProviderInterface;
use Derafu\Twig\Service\TwigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The HTML of the Markdown goes inside a wrapper template, unless there is none.
 */
#[CoversClass(MarkdownHtmlEngine::class)]
#[UsesClass(ConfigurationException::class)]
final class MarkdownHtmlEngineTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu-renderer-markdown-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        file_put_contents($this->directory . '/page.md', "Hello **World**\n");
    }

    protected function tearDown(): void
    {
        unlink($this->directory . '/page.md');
        rmdir($this->directory);
    }

    private function engine(?string $wrapper = 'html'): MarkdownHtmlEngine
    {
        $twig = new TwigService(new Environment(new ArrayLoader([
            'html.html.twig' => '<main>{{ content|raw }}</main>',
            'other.html.twig' => '<section class="{{ options.kind ?? \'none\' }}">{{ content|raw }}</section>',
        ])));

        return new MarkdownHtmlEngine(new MarkdownService(paths: [$this->directory]), $twig, $wrapper);
    }

    public function testTheHtmlOfTheMarkdownIsInsideTheWrapper(): void
    {
        $html = $this->engine()->render($this->directory . '/page.md');

        $this->assertStringStartsWith('<main>', $html);
        $this->assertStringContainsString('<strong>World</strong>', $html);
        $this->assertStringEndsWith('</main>', $html);
    }

    public function testAContentCanBeRenderedFromAString(): void
    {
        $html = $this->engine()->renderFromString('Some *text*');

        $this->assertStringStartsWith('<main>', $html);
        $this->assertStringContainsString('<em>text</em>', $html);
    }

    public function testTheWrapperOfTheDataAndTheOneOfTheOptionsGoBeforeTheOneOfTheEngine(): void
    {
        $engine = $this->engine();

        $this->assertStringStartsWith('<section class="none">', $engine->renderFromString('x', ['wrapperTemplate' => 'other']));
        $this->assertStringStartsWith('<section class="none">', $engine->renderFromString('x', [], ['wrapperTemplate' => 'other']));
        // The data go before the options.
        $this->assertStringStartsWith('<main>', $engine->renderFromString('x', ['wrapperTemplate' => 'html'], ['wrapperTemplate' => 'other']));
    }

    public function testTheOptionsAreInTheContextOfTheWrapper(): void
    {
        $html = $this->engine()->renderFromString('x', [], ['wrapperTemplate' => 'other', 'kind' => 'wide']);

        $this->assertStringStartsWith('<section class="wide">', $html);
    }

    /**
     * @return array<string, array{?string, array<string,mixed>, array<string,mixed>}>
     */
    public static function provideWithoutWrapper(): array
    {
        return [
            'false in the data' => ['html', ['wrapperTemplate' => false], []],
            'false in the options' => ['html', [], ['wrapperTemplate' => false]],
            'null in the engine' => [null, [], []],
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     */
    #[DataProvider('provideWithoutWrapper')]
    public function testWithoutWrapperItIsTheHtmlOfTheMarkdown(?string $wrapper, array $data, array $options): void
    {
        $html = $this->engine($wrapper)->renderFromString('Some *text*', $data, $options);

        $this->assertSame("<div class=\"markdown-body\"><p>Some <em>text</em></p>\n</div>", $html);
        $this->assertSame(
            "<div class=\"markdown-body\"><p>Hello <strong>World</strong></p>\n</div>",
            $this->engine($wrapper)->render($this->directory . '/page.md', $data, $options)
        );
    }

    /**
     * @return array<string, array{array<string,mixed>, array<string,mixed>, string}>
     */
    public static function provideWrappersThatAreNotValid(): array
    {
        return [
            'a wrapper that is an array' => [
                ['wrapperTemplate' => ['html']],
                [],
                'Invalid value "array" for configuration option "wrapperTemplate". Expected: string or false.',
            ],
            'a wrapper that is true' => [
                [],
                ['wrapperTemplate' => true],
                'Invalid value "bool" for configuration option "wrapperTemplate". Expected: string or false.',
            ],
            'options that are not an array' => [
                ['options' => 'text'],
                [],
                'Invalid value "string" for configuration option "options". Expected: array.',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     */
    #[DataProvider('provideWrappersThatAreNotValid')]
    public function testAWrapperOfTheWrongTypeIsATranslatableError(array $data, array $options, string $message): void
    {
        $exception = null;
        try {
            $this->engine()->renderFromString('x', $data, $options);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testItIsTheEngineOfTheMarkdownFiles(): void
    {
        $engine = $this->engine();

        $this->assertSame('markdown', $engine->getName());
        $this->assertSame(['md', 'markdown'], $engine->getSupportedExtensions());
    }

    public function testSharedPathsFindMarkdownFilesBeforeServiceFallbackPaths(): void
    {
        $provider = new class ($this->directory) implements TemplatePathProviderInterface {
            public function __construct(private readonly string $directory)
            {
            }

            public function getPaths(): array
            {
                return [$this->directory];
            }
        };
        $twig = new TwigService(new Environment(new ArrayLoader()));
        $engine = new MarkdownHtmlEngine(new MarkdownService(), $twig, null, templatePathProvider: $provider);
        self::assertStringContainsString('<strong>World</strong>', $engine->render('page'));
        self::assertStringContainsString('<strong>World</strong>', $engine->render($this->directory . '/page.md'));
    }

    public function testTheMarkdownServicesOwnPathsRemainAFallback(): void
    {
        $provider = new class () implements TemplatePathProviderInterface {
            public function getPaths(): array
            {
                return [];
            }
        };
        $twig = new TwigService(new Environment(new ArrayLoader()));
        $engine = new MarkdownHtmlEngine(new MarkdownService(paths: [$this->directory]), $twig, null, templatePathProvider: $provider);
        self::assertStringContainsString('<strong>World</strong>', $engine->render('page'));
    }
}
