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
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Twig\Contract\TwigServiceInterface;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * A PDF strategy that is not supported, or that is not implemented yet, is a
 * translatable error.
 */
#[CoversClass(HtmlPdfEngine::class)]
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
}
