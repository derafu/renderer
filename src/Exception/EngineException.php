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
 * Thrown when a rendering engine is not found or cannot be used.
 */
final class EngineException extends RendererException
{
    public static function forEngine(string $engine): static
    {
        return new static(['Rendering engine "{engine}" not found.', 'engine' => $engine]);
    }

    public static function forExtension(string $extension): static
    {
        return new static([
            'No engine found for extension "{extension}".',
            'extension' => $extension,
        ]);
    }

    public static function forUnsupportedFeature(
        string $engine,
        string $feature
    ): static {
        return new static([
            'Engine "{engine}" does not support feature: {feature}.',
            'engine' => $engine,
            'feature' => $feature,
        ]);
    }
}
