<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Renderer\Exception;

/**
 * Thrown when there is an error during template rendering.
 */
final class RenderingException extends RendererException
{
    public static function forTemplate(string $template, string $error): static
    {
        return new static(sprintf(
            'Error rendering template "%s": %s',
            $template,
            $error
        ));
    }
}
