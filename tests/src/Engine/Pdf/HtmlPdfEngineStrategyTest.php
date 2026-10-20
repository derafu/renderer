<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Engine\Pdf;

use Derafu\Renderer\Engine\Pdf\HtmlPdfEngine;
use Derafu\Renderer\Exception\ConfigurationException;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Twig\Contract\TwigServiceInterface;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * A PDF strategy that is not supported, or that is not implemented yet, is a
 * translatable error.
 */
#[CoversClass(HtmlPdfEngine::class)]
#[UsesClass(ConfigurationException::class)]
final class HtmlPdfEngineStrategyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function strategiesProvider(): array
    {
        return [
            'unknown' => ['other', 'Unsupported strategy for PDF rendering: other'],
            'chrome' => ['chrome', 'Writing HTML to PDF using Chrome is not yet supported.'],
        ];
    }

    #[DataProvider('strategiesProvider')]
    public function testAStrategyThatCanNotBeUsedIsATranslatableError(string $strategy, string $message): void
    {
        $twig = $this->createStub(TwigServiceInterface::class);
        $twig->method('renderFromString')->willReturn('<p>Hello</p>');

        $exception = null;
        try {
            (new HtmlPdfEngine($twig))->renderFromString('Hello', [], ['strategy' => $strategy]);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * @return array<string, array{array<string,mixed>, string}>
     */
    public static function configurationsThatAreNotValidProvider(): array
    {
        return [
            'config that is not an array' => [
                ['config' => 'text'],
                'Invalid value "string" for configuration option "config". Expected: array.',
            ],
            'pdf config that is not an array' => [
                ['config' => ['pdf' => 'text']],
                'Invalid value "string" for configuration option "config.pdf". Expected: array.',
            ],
            'pdf config that is a list' => [
                ['config' => ['pdf' => ['a', 'b']]],
                'Invalid value "list" for configuration option "config.pdf". Expected: array with string keys.',
            ],
            'local assets path that is not a string' => [
                ['config' => ['pdf' => ['local_assets_path' => ['/a']]]],
                'Invalid value "array" for configuration option "local_assets_path". Expected: string.',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $options
     */
    #[DataProvider('configurationsThatAreNotValidProvider')]
    public function testAConfigurationOfTheWrongTypeIsATranslatableError(array $options, string $message): void
    {
        $twig = $this->createStub(TwigServiceInterface::class);
        $twig->method('renderFromString')->willReturn('<p>Hello</p>');

        $exception = null;
        try {
            (new HtmlPdfEngine($twig))->renderFromString('Hello', [], $options);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testAnMpdfThatCanNotBeCreatedIsATranslatableErrorOfItsConfiguration(): void
    {
        $twig = $this->createStub(TwigServiceInterface::class);
        $twig->method('renderFromString')->willReturn('<p>Hello</p>');

        $exception = null;
        try {
            (new HtmlPdfEngine($twig))->renderFromString('Hello', [], [
                'config' => ['pdf' => ['format' => 'ZZZ']],
            ]);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertStringStartsWith('Invalid value "{', $exception->getMessage());
        $this->assertStringContainsString('"format":"ZZZ"', $exception->getMessage());
        $this->assertStringContainsString('Unknown page format ZZZ', $exception->getMessage());
    }

    public function testItIsTheEngineOfThePdfTemplates(): void
    {
        $engine = new HtmlPdfEngine($this->createStub(TwigServiceInterface::class));

        $this->assertSame('pdf', $engine->getName());
        $this->assertSame(['pdf.twig'], $engine->getSupportedExtensions());
    }
}
