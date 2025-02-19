<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Renderer\Engine\Html;

use Derafu\Renderer\Contract\EngineInterface;
use Derafu\Renderer\Exception\RenderingException;
use Derafu\Twig\Contract\TwigServiceInterface;
use Twig\Error\Error as TwigError;

/**
 * Twig template engine implementation.
 */
class TwigHtmlEngine implements EngineInterface
{
    public function __construct(private TwigServiceInterface $twigService)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function render(
        string $template,
        array $data = [],
        array $options = []
    ): string {
        try {
            // Merge runtime options with template data.
            $context = array_replace_recursive(['options' => $options], $data);

            // Render template with the context.
            return $this->twigService->render($template, $context);
        } catch (TwigError $e) {
            throw RenderingException::forTemplate($template, $e->getMessage());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSupportedExtensions(): array
    {
        return ['html.twig', 'txt.twig'];
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'twig';
    }
}
