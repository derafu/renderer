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

use Derafu\Renderer\Contract\FormatterHandlerProviderInterface;
use Derafu\Renderer\Contract\HandlerFormatterInterface;

final class ProFormatterHandlerProvider implements FormatterHandlerProviderInterface
{
    public function __construct(private readonly HandlerFormatterInterface $handler)
    {
    }

    public function getHandlers(): array
    {
        return [
            'billing_document' => $this->handler,
            'common' => $this->handler,
            'payslip' => $this->handler,
        ];
    }
}
