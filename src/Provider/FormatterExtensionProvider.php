<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Renderer\Provider;

use Derafu\Renderer\Contract\FormatterInterface;
use Derafu\Renderer\Formatter\Extension\TwigFormatterExtension;
use Derafu\Twig\Contract\ExtensionProviderInterface;

final class FormatterExtensionProvider implements ExtensionProviderInterface
{
    public function __construct(private readonly FormatterInterface $formatter)
    {
    }

    public function getExtensions(array $options = []): iterable
    {
        yield new TwigFormatterExtension($this->formatter);
    }
}
