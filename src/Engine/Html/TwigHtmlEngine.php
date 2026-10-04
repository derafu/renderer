<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Renderer\Engine\Html;

use Derafu\Renderer\Contract\EngineInterface;
use Derafu\Twig\Contract\TwigServiceInterface;
use Twig\Environment;

/**
 * Twig template engine implementation.
 */
class TwigHtmlEngine implements EngineInterface
{
    /**
     * @param TwigServiceInterface $twigService
     */
    public function __construct(private TwigServiceInterface $twigService)
    {
    }

    /**
     * Returns the Twig environment this engine uses.
     *
     * It is the same one that renders the templates, with its loader, its
     * functions, filters, tags and extensions, so it can be used to work with
     * them: inspect what is registered, find a template, parse or compile one,
     * and so on.
     *
     * Rendering directly with it skips what this engine adds to the context
     * (the `options` variable): to render, use the renderer.
     *
     * @return Environment
     */
    public function getTwig(): Environment
    {
        return $this->twigService->getTwig();
    }

    /**
     * {@inheritDoc}
     */
    public function render(
        string $template,
        array $data = [],
        array $options = []
    ): string {
        // Merge runtime options with template data.
        $context = array_replace_recursive(['options' => $options], $data);

        // Render template with the context.
        return $this->twigService->render($template, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function renderFromString(
        string $content,
        array $data = [],
        array $options = []
    ): string {
        // Merge runtime options with template data.
        $context = array_replace_recursive(['options' => $options], $data);

        // Render template with the context.
        return $this->twigService->renderFromString($content, $context);
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
