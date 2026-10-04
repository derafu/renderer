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

use Derafu\Renderer\Contract\FormatterInterface;
use Derafu\Renderer\Engine\Html\PhpHtmlEngine;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Twig\Contract\TwigServiceInterface;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Rendering a string with the PHP engine is not supported, and it says so with
 * a translatable error.
 */
#[CoversClass(PhpHtmlEngine::class)]
final class PhpHtmlEngineTest extends TestCase
{
    public function testRenderingFromAStringIsATranslatableError(): void
    {
        $engine = new PhpHtmlEngine(
            $this->createStub(TwigServiceInterface::class),
            $this->createStub(FormatterInterface::class)
        );

        $exception = null;
        try {
            $engine->renderFromString('<p>Hello</p>');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('Rendering from string is not yet supported for PHP HTML engine.', $exception->getMessage());
    }
}
