<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Provider;

use Derafu\Renderer\Formatter\DataFormatter;
use Derafu\Renderer\Formatter\Extension\TwigFormatterExtension;
use Derafu\Renderer\Formatter\Handler\GenericFormatterHandler;
use Derafu\Renderer\Provider\FormatterExtensionProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(FormatterExtensionProvider::class)]
#[UsesClass(DataFormatter::class)]
#[UsesClass(TwigFormatterExtension::class)]
#[UsesClass(GenericFormatterHandler::class)]
final class FormatterExtensionProviderTest extends TestCase
{
    public function testTheExtensionUsesTheSuppliedFormatter(): void
    {
        $formatter = new DataFormatter(['money' => '%s units']);
        $twig = new Environment(new ArrayLoader(['amount' => '{{ value|format_as("money") }}']));
        foreach ((new FormatterExtensionProvider($formatter))->getExtensions() as $extension) {
            $twig->addExtension($extension);
        }
        self::assertSame('42 units', $twig->render('amount', ['value' => 42]));
        $formatter->registerHandler('money', '%s changed');
        self::assertSame('42 changed', $twig->render('amount', ['value' => 42]));
    }
}
