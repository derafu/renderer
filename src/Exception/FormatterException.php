<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Renderer\Exception;

/**
 * Thrown when there is an error during data formatting.
 */
final class FormatterException extends RendererException
{
    public static function forFormat(string $format): static
    {
        return new static(['Formatter "{format}" not found.', 'format' => $format]);
    }

    public static function forValue(mixed $value, string $format): static
    {
        return new static([
            'Cannot format value of type "{type}" using format "{format}".',
            'type' => get_debug_type($value),
            'format' => $format,
        ]);
    }

    public static function forHandler(string $handler, string $error): static
    {
        return new static([
            'Error in formatter handler "{handler}": {error}',
            'handler' => $handler,
            'error' => $error,
        ]);
    }
}
