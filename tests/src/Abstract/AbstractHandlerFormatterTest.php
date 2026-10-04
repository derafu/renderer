<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Abstract;

use Derafu\Renderer\Abstract\AbstractHandlerFormatter;
use Derafu\Renderer\Exception\FormatterException;
use Derafu\Translation\Contract\TranslatableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * A format that has no handler is a translatable error.
 */
#[CoversClass(AbstractHandlerFormatter::class)]
#[UsesClass(FormatterException::class)]
final class AbstractHandlerFormatterTest extends TestCase
{
    public function testAFormatWithoutAHandlerIsATranslatableError(): void
    {
        $formatter = new class () extends AbstractHandlerFormatter {
            protected function createHandlers(): array
            {
                return [];
            }
        };

        $exception = null;
        try {
            $formatter->handle('value', 'unknown');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(FormatterException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('Handler for the format "unknown" not found.', $exception->getMessage());
    }
}
