# Derafu: Renderer - Unified Template Rendering Made Simple For PHP

![GitHub last commit](https://img.shields.io/github/last-commit/derafu/renderer/main)
![CI Workflow](https://github.com/derafu/renderer/actions/workflows/ci.yml/badge.svg?branch=main&event=push)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/derafu/renderer)
![GitHub Issues](https://img.shields.io/github/issues-raw/derafu/renderer)
![Total Downloads](https://poser.pugx.org/derafu/renderer/downloads)
![Monthly Downloads](https://poser.pugx.org/derafu/renderer/d/monthly)

A modern, flexible PHP template rendering library that provides a unified interface for multiple template engines and output formats.

## Features

- 🔄 **Unified Interface**: One consistent API for all template engines.
- 🚀 **Multiple Engine Support**: Works with Twig, PHP, Markdown and more.
- 📄 **Multiple Output Formats**: Generate HTML, PDF from any template.
- 🔌 **Extensible Architecture**: Easy to add new engines and formats.
- 🎨 **Powerful Formatting System**: Format data consistently across all templates.
- 🛡️ **Secure by Design**: Safe template rendering and file handling.
- 🪶 **Lightweight Core**: Only load what you need.
- ⚡ **Framework Agnostic**: Use with any PHP framework.

## Why Derafu\Renderer?

Traditional template systems often lock you into a single engine or require different handling for each format. Derafu\Renderer solves this by providing:

- A single, clean API for all template engines.
- Seamless switching between output formats.
- Consistent data formatting across all templates.
- Framework-agnostic design.
- Easy integration with existing systems.

## Installation

Install via Composer:

```bash
composer require derafu/renderer
```

## Basic Usage

```php
use Derafu\Renderer\Factory\RendererFactory;

// Create renderer with engines Twig and PDF.
$renderer = RendererFactory::create([
    'engines' => ['twig', 'pdf'],
    'paths' => ['/path/to/templates'],
]);

// Render templates in different engines.
$html = $renderer->render('template.html.twig', ['name' => 'John']);
$pdf = $renderer->render('template.html.twig', ['name' => 'John'], ['engine' => 'pdf']);
```

## Template Engines

### Twig Templates
```php
// template.html.twig
<h1>Hello {{ name }}!</h1>
<p>Today is {{ date|format_as('date.long') }}</p>
```

### PHP Templates
```php
// template.php
<h1>Hello <?= $name ?>!</h1>
<p>Today is <?= $format_as($date, 'date.long') ?></p>
```

### Markdown Templates
```markdown
# Hello {{ name }}!

Today is {{ date }}
```

## Advanced Usage

### Custom Engine Configuration
```php
$renderer = RendererFactory::create([
    'engines' => ['twig', 'markdown', 'pdf'],
    'paths' => ['/path/to/templates'],
    'formatters' => [
        'date' => function (string $date): string {
            $timestamp = strtotime($date);
            return date('d/m/Y', $timestamp);
        },
    ],
]);
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
