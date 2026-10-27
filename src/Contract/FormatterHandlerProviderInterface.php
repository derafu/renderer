<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Renderer\Contract;

interface FormatterHandlerProviderInterface
{
    /**
     * Named format handlers contributed by a domain.
     *
     * @return array<string, string|array<mixed>|object>
     */
    public function getHandlers(): array;
}
