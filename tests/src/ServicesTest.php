<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer;

use Composer\InstalledVersions;
use Derafu\Renderer\Contract\FormatterInterface;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Renderer\Exception\ConfigurationException;
use Derafu\Renderer\Exception\EngineException;
use Derafu\Renderer\Formatter\Extension\TwigFormatterExtension;
use Derafu\Renderer\Translation\RendererTranslationResourceProvider;
use Derafu\Twig\Contract\TwigServiceInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Imports the actual package configuration and checks its provider both as a
 * service and through the translator of a consuming application.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function loadContainer(?string $fixture = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $root = dirname(__DIR__, 2);
        $container->setParameter('fixture.templates', $root . '/tests/fixtures/services');
        $dependencyConfigurations = [];
        foreach (['derafu/twig', 'derafu/markdown'] as $package) {
            $dependencyConfigurations[] = (InstalledVersions::getInstallPath($package)
                ?? throw new RuntimeException($package . ' must be installed for these tests.')) . '/resources/config';
        }
        // Production imports use Composer's sibling-package layout. These
        // search roots also allow loading the same YAML from a source checkout.
        (new YamlFileLoader($container, new FileLocator([$root . '/resources/config', ...$dependencyConfigurations])))->load(
            $fixture === null ? 'renderer-services.yaml' : $root . '/tests/fixtures/config/' . $fixture
        );

        return $container;
    }

    private function compile(ContainerBuilder $container): void
    {
        // Keep package services reachable so unused-private-service removal
        // cannot hide an invalid definition from the construction checks.
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }
        $container->compile();
    }

    public function testTheProductionYamlProvidesAWorkingRenderer(): void
    {
        $container = $this->loadContainer();
        $this->compile($container);
        $renderer = $container->get(RendererInterface::class);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertSame('Hello Ada', $renderer->renderFromString('Hello {{ name }}', ['name' => 'Ada']));
    }

    public function testDomainsContributePathsWithoutLosingOtherDomains(): void
    {
        $container = $this->loadContainer('consumer.yaml');
        $this->compile($container);
        $renderer = $container->get(RendererInterface::class);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertSame("SITE\n", $renderer->render('shared'));
        self::assertSame("CORE only\n", $renderer->render('core-only'));
        self::assertSame("PRO only\n", $renderer->render('pro-only'));
        self::assertSame("SITE only\n", $renderer->render('site-only'));
    }

    public function testProviderPriorityCanPreserveALibraryFirstSearchOrder(): void
    {
        $container = $this->loadContainer('library-first.yaml');
        $this->compile($container);
        $renderer = $container->get(RendererInterface::class);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertSame("PRO\n", $renderer->render('shared'));
    }

    public function testHandlerContributionsAndOverridesReachTwigAndDirectFormatting(): void
    {
        $container = $this->loadContainer('consumer.yaml');
        $this->compile($container);
        $renderer = $container->get(RendererInterface::class);
        $formatter = $container->get(FormatterInterface::class);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertInstanceOf(FormatterInterface::class, $formatter);
        self::assertSame('PRO:amount:42', $formatter->format(42, 'billing_document.amount'));
        self::assertSame('CORE:amount:42', $formatter->format(42, 'core_only.amount'));
        self::assertSame("PRO:amount:42|CORE:amount:42|PRO:amount:42|PRO:amount:42|SITE:amount:42\n", $renderer->render('formats', ['amount' => 42]));
        $formatter->registerHandler('billing_document', ['42' => 'shared override']);
        self::assertStringStartsWith('shared override|', $renderer->render('formats', ['amount' => 42]));
    }

    public function testThePhpEngineUsesTheSharedTemplatePathProviders(): void
    {
        $container = $this->loadContainer('php.yaml');
        $this->compile($container);
        $renderer = $container->get(RendererInterface::class);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertSame('<main>SITE</main>', $renderer->render('shared.php'));
    }

    public function testTheMarkdownEngineUsesTheSharedTemplatePathProviders(): void
    {
        $container = $this->loadContainer('markdown.yaml');
        $this->compile($container);
        $renderer = $container->get(RendererInterface::class);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertStringContainsString('<strong>SITE</strong>', $renderer->render('shared.md'));
        self::assertStringContainsString('<strong>String</strong>', $renderer->renderFromString('**String**', options: ['engine' => 'markdown']));
    }

    public function testThePdfEngineRendersARealPdfWithTheSharedFormatter(): void
    {
        $container = $this->loadContainer('pdf.yaml');
        $this->compile($container);
        $renderer = $container->get(RendererInterface::class);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        $pdf = $renderer->renderFromString('<p>{{ amount|format_as("billing_document.amount") }}</p>', ['amount' => 42], ['engine' => 'pdf']);
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%%EOF', $pdf);
    }

    public function testExistingTwigEnvironmentsRemainUnmodified(): void
    {
        $container = $this->loadContainer('existing-environment.yaml');
        $this->compile($container);
        $renderer = $container->get(RendererInterface::class);
        $twig = $container->get(TwigServiceInterface::class);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertInstanceOf(TwigServiceInterface::class, $twig);
        self::assertSame($container->get(Environment::class), $twig->getTwig());
        self::assertTrue($twig->getTwig()->isStrictVariables());
        self::assertFalse($twig->getTwig()->hasExtension(TwigFormatterExtension::class));
        self::assertSame('Native preserved', $renderer->render('native'));
    }

    public function testRenderersCanUseIndependentEnvironments(): void
    {
        $container = $this->loadContainer('isolated.yaml');
        $this->compile($container);
        $default = $container->get(RendererInterface::class);
        $isolated = $container->get(RendererInterface::class . ' $isolated');
        self::assertInstanceOf(RendererInterface::class, $default);
        self::assertInstanceOf(RendererInterface::class, $isolated);
        self::assertSame("SITE\n", $default->render('shared'));
        self::assertSame("CORE\n", $isolated->render('shared'));
    }

    public function testCompositionWorksInACompiledContainer(): void
    {
        $container = $this->loadContainer('consumer.yaml');
        $this->compile($container);
        $class = 'RendererServicesIntegrationContainer';
        $code = '';
        if (!class_exists($class, false)) {
            $dump = (new PhpDumper($container))->dump(['class' => $class]);
            self::assertIsString($dump);
            $code = substr($dump, 5);
        }
        $compiled = eval($code . "\nreturn new " . $class . '();');
        self::assertInstanceOf(ContainerInterface::class, $compiled);
        $renderer = $compiled->get(RendererInterface::class);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertSame("SITE\n", $renderer->render('shared'));
        self::assertStringStartsWith('PRO:amount:42|CORE:amount:42|', $renderer->render('formats', ['amount' => 42]));
    }

    public function testEveryConfiguredServiceCanBeConstructed(): void
    {
        $container = $this->loadContainer();
        $this->compile($container);
        self::assertInstanceOf(RendererTranslationResourceProvider::class, $container->get(RendererTranslationResourceProvider::class));
    }

    public function testTheTaggedProviderExposesThePackageCatalogue(): void
    {
        $container = $this->loadContainer();
        self::assertArrayHasKey(
            RendererTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $this->compile($container);
        $provider = $container->get(RendererTranslationResourceProvider::class);
        self::assertInstanceOf(RendererTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        self::assertCount(1, $directories);
        self::assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
        self::assertFileExists($directories[0] . '/errors+intl-icu.es.php');
    }

    public function testTaggedResourcesTranslateARealMissingEngineError(): void
    {
        $container = $this->loadContainer('translation.yaml');
        $this->compile($container);
        $translator = $container->get(TranslatorInterface::class);
        $renderer = $container->get(RendererInterface::class);
        self::assertInstanceOf(Translator::class, $translator);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        try {
            $renderer->renderFromString('content', options: ['engine' => 'missing']);
            self::fail('Rendering with an unknown engine was accepted.');
        } catch (EngineException $exception) {
            self::assertSame('No se encontró el motor de renderizado "missing".', $exception->trans($translator));
        }
    }

    public function testTaggedResourcesTranslateARealConfigurationErrorWithItsParameters(): void
    {
        $container = $this->loadContainer('translation.yaml');
        $this->compile($container);
        $translator = $container->get(TranslatorInterface::class);
        $renderer = $container->get(RendererInterface::class);
        self::assertInstanceOf(Translator::class, $translator);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        try {
            $renderer->renderFromString('content', options: ['engine' => ['invalid']]);
            self::fail('An array was accepted as the engine name.');
        } catch (ConfigurationException $exception) {
            self::assertSame(
                'Valor inválido "array" para la opción de configuración "engine". Se esperaba: string.',
                $exception->trans($translator)
            );
        }
    }
}
