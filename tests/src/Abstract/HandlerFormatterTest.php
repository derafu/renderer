<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Document and Template Rendering Library.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Abstract;

use ArrayObject;
use Derafu\Renderer\Abstract\AbstractHandlerFormatter;
use Derafu\Renderer\Exception\FormatterException;
use Derafu\Renderer\Formatter\Handler\GenericFormatterHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * A formatter with a map of handlers: its formats are the keys of the map, and a
 * format can be the alias of another one.
 */
#[CoversClass(AbstractHandlerFormatter::class)]
#[UsesClass(GenericFormatterHandler::class)]
#[UsesClass(FormatterException::class)]
final class HandlerFormatterTest extends TestCase
{
    /**
     * @param ArrayObject<string,int> $counter
     */
    private function formatter(ArrayObject $counter = new ArrayObject(['created' => 0])): AbstractHandlerFormatter
    {
        return new class ($counter) extends AbstractHandlerFormatter {
            /**
             * @param ArrayObject<string,int> $counter
             */
            public function __construct(private ArrayObject $counter)
            {
            }

            /**
             * @return array<string,string|array<mixed>|object>
             */
            protected function createHandlers(): array
            {
                $this->counter['created']++;

                return [
                    'upper' => fn (string $value): string => strtoupper($value),
                    'shout' => 'alias:upper',
                    'bracket' => '[%s]',
                    'yesno' => [1 => 'Yes', 0 => 'No'],
                ];
            }
        };
    }

    public function testItFormatsWithTheHandlerOfTheFormat(): void
    {
        $formatter = $this->formatter();

        $this->assertSame('HELLO', $formatter->handle('hello', 'upper'));
        $this->assertSame('[x]', $formatter->handle('x', 'bracket'));
        $this->assertSame('Yes', $formatter->handle(1, 'yesno'));
    }

    public function testAnAliasUsesTheHandlerOfTheOtherFormat(): void
    {
        $this->assertSame('HELLO', $this->formatter()->handle('hello', 'shout'));
    }

    public function testItsFormatsAreTheKeysOfTheMap(): void
    {
        $this->assertSame(['upper', 'shout', 'bracket', 'yesno'], $this->formatter()->getSupportedFormats());
    }

    public function testTheHandlersAreCreatedOnlyOnce(): void
    {
        $counter = new ArrayObject(['created' => 0]);
        $formatter = $this->formatter($counter);

        $formatter->handle('a', 'upper');
        $formatter->handle('b', 'upper');
        $formatter->getSupportedFormats();

        $this->assertSame(1, $counter['created']);
    }
}
