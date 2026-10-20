<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Document and Template Rendering Library.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Formatter\Extension;

use Derafu\Renderer\Formatter\DataFormatter;
use Derafu\Renderer\Formatter\Extension\TwigFormatterExtension;
use Derafu\Renderer\Formatter\Handler\GenericFormatterHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Stringable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The filters of the formatter in the templates.
 */
#[CoversClass(TwigFormatterExtension::class)]
#[UsesClass(DataFormatter::class)]
#[UsesClass(GenericFormatterHandler::class)]
final class TwigFormatterExtensionTest extends TestCase
{
    /**
     * @param array<string,mixed> $context
     */
    private function render(string $template, array $context = []): string
    {
        $twig = new Environment(new ArrayLoader(['t' => $template]));
        $twig->addExtension(new TwigFormatterExtension(new DataFormatter([
            'upper' => fn (string $value): string => strtoupper($value),
        ])));

        return $twig->render('t', $context);
    }

    public function testToStringGivesTheTextOfAScalarAndNothingForNull(): void
    {
        $this->assertSame('7', $this->render('{{ 7|to_string }}'));
        $this->assertSame('text', $this->render("{{ 'text'|to_string }}"));
        $this->assertSame('', $this->render('{{ null|to_string }}'));
    }

    public function testToStringGivesTheTextOfAnObjectThatCanBeAString(): void
    {
        $value = new class () implements Stringable {
            public function __toString(): string
            {
                return 'an object';
            }
        };

        $this->assertSame('an object', $this->render('{{ value|to_string }}', ['value' => $value]));
    }

    public function testToStringGivesJsonForAnArray(): void
    {
        $html = $this->render("{{ {'key': 'value', 'list': [1, 2]}|to_string }}");

        $this->assertSame(
            "{\n    \"key\": \"value\",\n    \"list\": [\n        1,\n        2\n    ]\n}",
            $html
        );
    }

    public function testFormatAsUsesTheHandlerOfTheFormatter(): void
    {
        $this->assertSame('HELLO', $this->render("{{ 'hello'|format_as('upper') }}"));
    }
}
