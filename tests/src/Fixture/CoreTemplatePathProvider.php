<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Fixture;

use Derafu\Twig\Contract\TemplatePathProviderInterface;

final class CoreTemplatePathProvider implements TemplatePathProviderInterface
{
    public function __construct(private readonly string $root)
    {
    }

    public function getPaths(): array
    {
        return [$this->root . '/core'];
    }
}
