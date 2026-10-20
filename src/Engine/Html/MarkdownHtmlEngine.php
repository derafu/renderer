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

use Derafu\Markdown\Contract\MarkdownServiceInterface;
use Derafu\Renderer\Contract\EngineInterface;
use Derafu\Renderer\Exception\ConfigurationException;
use Derafu\Twig\Contract\TwigServiceInterface;

/**
 * Markdown template engine implementation using CommonMark.
 */
class MarkdownHtmlEngine implements EngineInterface
{
    /**
     * @param MarkdownServiceInterface $markdownService
     * @param TwigServiceInterface $twigService
     * @param ?string $wrapperTemplate
     * @param string $contentVarName
     */
    public function __construct(
        private MarkdownServiceInterface $markdownService,
        private TwigServiceInterface $twigService,
        private readonly ?string $wrapperTemplate = 'html',
        private readonly string $contentVarName = 'content'
    ) {
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
        $html = $this->markdownService->render($template, $context);

        // Determine if we need to render a wrapper template.
        $wrapperTemplate = $this->wrapperTemplate($context);
        if ($wrapperTemplate === null) {
            return $html;
        }

        // Render wrapper with twig template.
        $wrapperContext = array_merge(
            $context,
            [$this->contentVarName => $html]
        );

        return $this->twigService->render($wrapperTemplate, $wrapperContext);
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
        $html = $this->markdownService->renderFromString($content, $context);

        // Determine if we need to render a wrapper template.
        $wrapperTemplate = $this->wrapperTemplate($context);
        if ($wrapperTemplate === null) {
            return $html;
        }

        // Render wrapper with twig template.
        $wrapperContext = array_merge(
            $context,
            [$this->contentVarName => $html]
        );

        return $this->twigService->render($wrapperTemplate, $wrapperContext);
    }

    /**
     * The wrapper template for the HTML of the Markdown: the one of the data,
     * the one of the options or the one of the engine, in that order.
     *
     * @param array<mixed> $context The data with the runtime options (in
     * `options`).
     * @return string|null The template, or `null` if there is no wrapper (the
     * value `false` or `null` where it is set means that).
     * @throws ConfigurationException If `options` is not an array or the wrapper
     * is not a string or `false`.
     */
    private function wrapperTemplate(array $context): ?string
    {
        $options = $context['options'] ?? [];
        if (!is_array($options)) {
            throw ConfigurationException::forInvalidOption('options', get_debug_type($options), 'array');
        }

        $wrapperTemplate = $context['wrapperTemplate']
            ?? $options['wrapperTemplate']
            ?? $this->wrapperTemplate
        ;

        if ($wrapperTemplate === false) {
            return null;
        }

        if ($wrapperTemplate !== null && !is_string($wrapperTemplate)) {
            throw ConfigurationException::forInvalidOption(
                'wrapperTemplate',
                get_debug_type($wrapperTemplate),
                'string or false'
            );
        }

        return $wrapperTemplate;
    }

    /**
     * {@inheritDoc}
     */
    public function getSupportedExtensions(): array
    {
        return ['md', 'markdown'];
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'markdown';
    }
}
