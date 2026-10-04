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
 * Thrown when there is a configuration error.
 */
final class ConfigurationException extends RendererException
{
    public static function forMissingOption(string $option): static
    {
        return new static([
            'Required configuration option "{option}" is missing.',
            'option' => $option,
        ]);
    }

    public static function forInvalidOption(
        string $option,
        string $value,
        string $expected
    ): static {
        return new static([
            'Invalid value "{value}" for configuration option "{option}". Expected: {expected}.',
            'value' => $value,
            'option' => $option,
            'expected' => $expected,
        ]);
    }
}
