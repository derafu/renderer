<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRenderer\Engine\Html;

use Derafu\Renderer\Contract\FormatterInterface;
use Derafu\Renderer\Engine\Html\PhpHtmlEngine;
use Derafu\Renderer\Exception\TemplateNotFoundException;
use Derafu\Renderer\Formatter\DataFormatter;
use Derafu\Renderer\Formatter\Handler\GenericFormatterHandler;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Twig\Contract\TemplatePathProviderInterface;
use Derafu\Twig\Contract\TwigServiceInterface;
use Derafu\Twig\Service\TwigService;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The PHP engine renders PHP templates inside a wrapper template; rendering a
 * string is not supported, and it says so with a translatable error.
 */
#[CoversClass(PhpHtmlEngine::class)]
#[UsesClass(TemplateNotFoundException::class)]
#[UsesClass(DataFormatter::class)]
#[UsesClass(GenericFormatterHandler::class)]
final class PhpHtmlEngineTest extends TestCase
{
    public function testRenderingFromAStringIsATranslatableError(): void
    {
        $engine = new PhpHtmlEngine(
            $this->createStub(TwigServiceInterface::class),
            $this->createStub(FormatterInterface::class)
        );

        $exception = null;
        try {
            $engine->renderFromString('<p>Hello</p>');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('Rendering from string is not yet supported for PHP HTML engine.', $exception->getMessage());
    }

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu-renderer-php-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    /**
     * @param array<string,string> $templates The PHP templates, by file name.
     */
    private function engine(array $templates, string $wrapper = 'html'): PhpHtmlEngine
    {
        foreach ($templates as $name => $code) {
            file_put_contents($this->directory . '/' . $name, $code);
        }

        $twig = new TwigService(new Environment(new ArrayLoader([
            'html.html.twig' => '<main>{{ content|raw }}</main>',
            'other.html.twig' => '<aside>{{ content|raw }}|{{ __view_title ?? "-" }}</aside>',
        ])));

        return new PhpHtmlEngine($twig, new DataFormatter(['upper' => fn (string $v): string => strtoupper($v)]), [$this->directory], $wrapper);
    }

    public function testATemplateIsRenderedWithItsDataInsideTheWrapper(): void
    {
        $engine = $this->engine(['page.php' => '<p><?= $name ?></p>']);

        $this->assertSame('<main><p>World</p></main>', $engine->render('page', ['name' => 'World']));
    }

    public function testATemplateIsFoundByItsNameWithOrWithoutExtensionOrByItsPath(): void
    {
        $engine = $this->engine(['page.php' => 'A', 'view.phtml' => 'B']);

        $this->assertSame('<main>A</main>', $engine->render('page.php'));
        $this->assertSame('<main>B</main>', $engine->render('view.phtml'));
        $this->assertSame('<main>A</main>', $engine->render($this->directory . '/page.php'));
    }

    public function testATemplateThatDoesNotExistIsATranslatableError(): void
    {
        $engine = $this->engine([]);

        $exception = null;
        try {
            $engine->render('missing');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(TemplateNotFoundException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('Template "missing.php" not found or is not readable.', $exception->getMessage());
    }

    public function testTheHelpersOfTheFormatterAreInTheTemplate(): void
    {
        $engine = $this->engine(['page.php' => '<?= $format_as($name, "upper") ?>|<?= $to_string($list) ?>|<?= $to_string(null) ?>|<?= $to_string(7) ?>']);

        $html = $engine->render('page', ['name' => 'world', 'list' => [1]]);

        $this->assertSame("<main>WORLD|[\n    1\n]||7</main>", $html);
    }

    public function testTheOptionsAreInTheDataOfTheTemplate(): void
    {
        $engine = $this->engine(['page.php' => '<?= $options["mood"] ?>']);

        $this->assertSame('<main>fine</main>', $engine->render('page', [], ['mood' => 'fine']));
    }

    public function testTheWrapperOfTheDataGoesBeforeTheOneOfTheEngine(): void
    {
        $engine = $this->engine(['page.php' => 'x']);

        $this->assertSame('<aside>x|-</aside>', $engine->render('page', ['wrapperTemplate' => 'other']));
    }

    public function testTheVariablesWithThePrefixOfTheTemplateAreForTheWrapper(): void
    {
        $engine = $this->engine(['page.php' => '<?php $__view_title = "From the template"; ?>x'], 'other');

        $this->assertSame('<aside>x|From the template</aside>', $engine->render('page'));
    }

    public function testAnErrorOfTheTemplateIsNotHiddenAndItsOutputIsDiscarded(): void
    {
        $engine = $this->engine(['page.php' => 'Output before the error<?php throw new RuntimeException("Template failed."); ?>']);
        $level = ob_get_level();

        $exception = null;
        try {
            $engine->render('page');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame('Template failed.', $exception->getMessage());
        $this->assertSame($level, ob_get_level());
    }

    public function testItIsTheEngineOfThePhpTemplates(): void
    {
        $engine = $this->engine([]);

        $this->assertSame('php', $engine->getName());
        $this->assertSame(['php', 'phtml'], $engine->getSupportedExtensions());
    }

    public function testThePhpEngineReadsPathsFromAProvider(): void
    {
        file_put_contents($this->directory . '/provided.php', '<p><?= $name ?></p>');
        $provider = new class ($this->directory) implements TemplatePathProviderInterface {
            public function __construct(private readonly string $directory)
            {
            }

            public function getPaths(): array
            {
                return [$this->directory];
            }
        };
        $twig = new TwigService(new Environment(new ArrayLoader(['html.html.twig' => '<main>{{ content|raw }}</main>'])));
        $engine = new PhpHtmlEngine($twig, new DataFormatter(), $provider);
        self::assertSame('<main><p>Ada</p></main>', $engine->render('provided', ['name' => 'Ada']));
    }
}
