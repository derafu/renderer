<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Exception;

use Closure;
use Derafu\Renderer\Exception\ConfigurationException;
use Derafu\Renderer\Exception\EngineException;
use Derafu\Renderer\Exception\FormatterException;
use Derafu\Renderer\Exception\RendererException;
use Derafu\Renderer\Exception\TemplateNotFoundException;
use Derafu\Translation\Contract\TranslatableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The exceptions of the package can be translated, and say the same as before.
 */
#[CoversClass(RendererException::class)]
#[CoversClass(ConfigurationException::class)]
#[CoversClass(EngineException::class)]
#[CoversClass(FormatterException::class)]
#[CoversClass(TemplateNotFoundException::class)]
final class RendererExceptionTest extends TestCase
{
    /**
     * @return array<string, array{Closure, string}>
     */
    public static function exceptionsProvider(): array
    {
        return [
            'missing option' => [
                fn () => ConfigurationException::forMissingOption('engines'),
                'Required configuration option "engines" is missing.',
            ],
            'invalid option' => [
                fn () => ConfigurationException::forInvalidOption('engines', 'x', 'an array'),
                'Invalid value "x" for configuration option "engines". Expected: an array.',
            ],
            'engine' => [
                fn () => EngineException::forEngine('pdf'),
                'Rendering engine "pdf" not found.',
            ],
            'extension' => [
                fn () => EngineException::forExtension('xyz'),
                'No engine found for extension "xyz".',
            ],
            'unsupported feature' => [
                fn () => EngineException::forUnsupportedFeature('php', 'strings'),
                'Engine "php" does not support feature: strings.',
            ],
            'format' => [
                fn () => FormatterException::forFormat('money'),
                'Formatter "money" not found.',
            ],
            'value' => [
                fn () => FormatterException::forValue([], 'money'),
                'Cannot format value of type "array" using format "money".',
            ],
            'handler' => [
                fn () => FormatterException::forHandler('money', 'Failed.'),
                'Error in formatter handler "money": Failed.',
            ],
            'template' => [
                fn () => TemplateNotFoundException::forTemplate('home'),
                'Template "home" not found or is not readable.',
            ],
            'path' => [
                fn () => TemplateNotFoundException::forPath('/templates'),
                'Template path "/templates" not found or is not readable.',
            ],
        ];
    }

    #[DataProvider('exceptionsProvider')]
    public function testEveryExceptionIsTranslatableAndSaysTheSame(Closure $create, string $message): void
    {
        $exception = $create();

        $this->assertInstanceOf(RendererException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }
}
