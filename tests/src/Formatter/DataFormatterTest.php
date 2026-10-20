<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Document and Template Rendering Library.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Formatter;

use Derafu\Renderer\Contract\HandlerFormatterInterface;
use Derafu\Renderer\Exception\FormatterException;
use Derafu\Renderer\Formatter\DataFormatter;
use Derafu\Renderer\Formatter\Handler\GenericFormatterHandler;
use Derafu\Translation\Contract\TranslatableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Formats a value with the handler of its format: by its name, by the format
 * that a handler supports, by the default one or, without any, as a mask.
 */
#[CoversClass(DataFormatter::class)]
#[UsesClass(GenericFormatterHandler::class)]
#[UsesClass(FormatterException::class)]
final class DataFormatterTest extends TestCase
{
    /**
     * A handler of formats that tells what it received.
     *
     * @param list<string> $supported
     */
    private function handler(array $supported): HandlerFormatterInterface
    {
        return new class ($supported) implements HandlerFormatterInterface {
            /**
             * @param list<string> $supported
             */
            public function __construct(private array $supported)
            {
            }

            public function handle(mixed $value, string $format): string
            {
                return $format . ':' . (is_scalar($value) ? $value : get_debug_type($value));
            }

            public function getSupportedFormats(): array
            {
                return $this->supported;
            }
        };
    }

    public function testNullIsAnEmptyTextWhateverTheFormat(): void
    {
        $this->assertSame('', (new DataFormatter(['upper' => 'x%s']))->format(null, 'upper'));
    }

    public function testAHandlerCanBeRegisteredAfterwards(): void
    {
        $formatter = new DataFormatter();

        $this->assertSame($formatter, $formatter->registerHandler('upper', fn (string $value): string => strtoupper($value)));
        $this->assertSame('HELLO', $formatter->format('hello', 'upper'));
    }

    public function testTheFormatThatIsTheNameOfAHandlerIsItsFormat(): void
    {
        $formatter = new DataFormatter(['money' => $this->handler(['other'])]);

        $this->assertSame('money:5', $formatter->format(5, 'money'));
    }

    public function testADotSeparatesTheHandlerFromItsFormat(): void
    {
        $formatter = new DataFormatter(['common' => $this->handler(['currency'])]);

        $this->assertSame('currency:5', $formatter->format(5, 'common.currency'));
    }

    public function testAHandlerThatSupportsTheFormatIsTheOneThatFormats(): void
    {
        $formatter = new DataFormatter([
            'first' => $this->handler(['a']),
            'second' => $this->handler(['b', 'c']),
        ]);

        $this->assertSame('c:5', $formatter->format(5, 'c'));
    }

    public function testTheHandlersThatAreNotFormattersAreSkippedWhenSearchingForTheFormat(): void
    {
        $formatter = new DataFormatter([
            'mask' => '[%s]',
            'map' => [1 => 'one'],
            'formatter' => $this->handler(['a']),
        ]);

        $this->assertSame('a:5', $formatter->format(5, 'a'));
    }

    public function testTheDefaultHandlerFormatsTheFormatsThatNoOneSupports(): void
    {
        $formatter = new DataFormatter(['first' => $this->handler(['a']), 'default' => $this->handler([])]);

        $this->assertSame('x:5', $formatter->format(5, 'x'));
    }

    public function testWithoutAHandlerTheFormatIsAMask(): void
    {
        $formatter = new DataFormatter(['first' => $this->handler(['a'])]);

        $this->assertSame('Total: 5', $formatter->format(5, 'Total: %s'));
    }

    public function testAHandlerThatIsNotAFormatterUsesTheGenericHandler(): void
    {
        $formatter = new DataFormatter(['mask' => '[%s]', 'map' => [1 => 'one']]);

        $this->assertSame('[5]', $formatter->format(5, 'mask'));
        $this->assertSame('one', $formatter->format(1, 'map'));
    }

    public function testAnErrorOfTheHandlerIsATranslatableErrorOfTheFormat(): void
    {
        $formatter = new DataFormatter([
            'broken' => function (): string {
                throw new RuntimeException('It does not work.');
            },
        ]);

        $exception = null;
        try {
            $formatter->format('x', 'broken');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(FormatterException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('Error in formatter handler "broken": It does not work.', $exception->getMessage());
    }
}
