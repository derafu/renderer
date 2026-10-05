<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    // Configuration.
    'Required configuration option "{option}" is missing.' =>
        'Falta la opción de configuración obligatoria "{option}".',
    'Invalid value "{value}" for configuration option "{option}". Expected: {expected}.' =>
        'Valor inválido "{value}" para la opción de configuración "{option}". Se esperaba: {expected}.',

    // Engines.
    'Rendering engine "{engine}" not found.' =>
        'No se encontró el motor de renderizado "{engine}".',
    'No engine found for extension "{extension}".' =>
        'No se encontró un motor para la extensión "{extension}".',
    'Engine "{engine}" does not support feature: {feature}.' =>
        'El motor "{engine}" no soporta la característica: {feature}.',
    'Unsupported strategy for PDF rendering: {strategy}' =>
        'Estrategia no soportada para generar PDF: {strategy}',
    'Writing HTML to PDF using Chrome is not yet supported.' =>
        'Escribir HTML a PDF usando Chrome aún no está soportado.',
    'Rendering from string is not yet supported for PHP HTML engine.' =>
        'Renderizar desde un string aún no está soportado para el motor HTML de PHP.',

    // Formatters.
    'Formatter "{format}" not found.' =>
        'No se encontró el formateador "{format}".',
    'Cannot format value of type "{type}" using format "{format}".' =>
        'No se puede formatear un valor de tipo "{type}" usando el formato "{format}".',
    'Error in formatter handler "{handler}": {error}' =>
        'Error en el manejador de formato "{handler}": {error}',
    'Handler for the format "{format}" not found.' =>
        'No se encontró el manejador para el formato "{format}".',
    'Serialization for data type {type} failed: {message}' =>
        'La serialización del tipo de dato {type} falló: {message}',

    // Templates.
    'Template "{template}" not found or is not readable.' =>
        'La plantilla "{template}" no se encontró o no se puede leer.',
    'Template path "{path}" not found or is not readable.' =>
        'La ruta de plantillas "{path}" no se encontró o no se puede leer.',
];
