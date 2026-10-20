<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Document and Template Rendering Library.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Formatter\Handler;

use Derafu\Renderer\Formatter\Handler\GenericFormatterHandler;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;

/**
 * A value that can not be turned into text is not an error that stops the
 * rendering: the text of the error is the formatted value.
 */
#[CoversClass(GenericFormatterHandler::class)]
final class GenericFormatterHandlerTest extends TestCase
{
    public function testAValueThatCanBeSerializedIsFormattedAsJson(): void
    {
        $this->assertSame(
            "{\n    \"a\": 1\n}",
            (new GenericFormatterHandler())->handle(['a' => 1])
        );
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function provideValuesThatCanNotBeSerialized(): array
    {
        return [
            'a number that is not a number' => [
                ['x' => NAN],
                'Serialization for data type array failed: Inf and NaN cannot be JSON encoded',
            ],
            'a text that is not valid' => [
                ["\xB1\x31"],
                'Serialization for data type array failed: Malformed UTF-8 characters, possibly incorrectly encoded',
            ],
            'an object that fails when it is serialized' => [
                new class () implements JsonSerializable {
                    public function jsonSerialize(): mixed
                    {
                        throw new RuntimeException('It {can not} be serialized: 100%.');
                    }
                },
                'Serialization for data type JsonSerializable@anonymous failed: It {can not} be serialized: 100%.',
            ],
        ];
    }

    #[DataProvider('provideValuesThatCanNotBeSerialized')]
    public function testAValueThatCanNotBeSerializedIsTheTextOfTheError(mixed $value, string $expected): void
    {
        $this->assertSame($expected, (new GenericFormatterHandler())->handle($value));
    }

    public function testAMapGivesTheTextOfTheValueThatItHas(): void
    {
        $handler = new GenericFormatterHandler();
        $map = [1 => 'Yes', 0 => 'No', '' => 'Nothing', 'a' => 'Letter'];

        $this->assertSame('Yes', $handler->handle(true, $map));
        $this->assertSame('No', $handler->handle(0, $map));
        $this->assertSame('Nothing', $handler->handle(null, $map));
        $this->assertSame('Letter', $handler->handle('a', $map));
        $this->assertSame('Letter', $handler->handle(new class () implements Stringable {
            public function __toString(): string
            {
                return 'a';
            }
        }, $map));
    }

    public function testAMapGivesTheValueThatItDoesNotHave(): void
    {
        $handler = new GenericFormatterHandler();

        $this->assertSame('other', $handler->handle('other', ['a' => 'Letter']));
    }

    public function testAValueThatCanNotBeAKeyOfAMapIsFormattedAsWithoutHandler(): void
    {
        $handler = new GenericFormatterHandler();

        $this->assertSame("{\n    \"a\": 1\n}", $handler->handle(['a' => 1], ['a' => 'Letter']));
    }

    public function testAMaskGetsTheTextOfAValueThatIsNotAScalar(): void
    {
        $handler = new GenericFormatterHandler();

        $this->assertSame('Value: 7', $handler->handle(7, 'Value: %d'));
        $this->assertSame('Value: 3.50', $handler->handle(3.5, 'Value: %.2f'));
        // An array, which has no text, is its JSON.
        $this->assertSame("Value: [\n    1\n]", $handler->handle([1], 'Value: %s'));
    }

    public function testAValueThatCanBeAStringIsItsTextWithAnyHandler(): void
    {
        $handler = new GenericFormatterHandler();
        $value = new class () implements Stringable {
            public function __toString(): string
            {
                return 'a';
            }
        };

        $this->assertSame('a', $handler->handle($value));
        $this->assertSame('Value: a', $handler->handle($value, 'Value: %s'));
        $this->assertSame('Letter', $handler->handle($value, ['a' => 'Letter']));
        $this->assertSame('A', $handler->handle($value, fn (Stringable $v): string => strtoupper((string) $v)));
    }

    public function testAnObjectWithFindIsARepositoryThatGivesTheValue(): void
    {
        $repository = new class () {
            public function find(mixed $id): mixed
            {
                return match ($id) {
                    1 => 'Ana',
                    2 => null,
                    default => 'Unknown',
                };
            }
        };
        $handler = new GenericFormatterHandler();

        $this->assertSame('Ana', $handler->handle(1, $repository));
        $this->assertSame('', $handler->handle(2, $repository));
        $this->assertSame('Unknown', $handler->handle(3, $repository));
    }

    public function testAnObjectThatIsNotAHandlerIsIgnoredAndTheValueIsFormatted(): void
    {
        $this->assertSame('value', (new GenericFormatterHandler())->handle('value', new stdClass()));
    }
}
