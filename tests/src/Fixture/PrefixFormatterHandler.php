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

use Derafu\Renderer\Contract\HandlerFormatterInterface;
use InvalidArgumentException;

final class PrefixFormatterHandler implements HandlerFormatterInterface
{
    public function __construct(private readonly string $prefix)
    {
    }

    public function handle(mixed $value, string $format): string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('This fixture formats scalar values only.');
        }

        return $this->prefix . ':' . $format . ':' . (string) $value;
    }

    public function getSupportedFormats(): array
    {
        return ['amount'];
    }
}
