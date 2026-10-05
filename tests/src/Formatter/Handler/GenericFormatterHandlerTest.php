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
}
