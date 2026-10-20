<?php

declare(strict_types=1);

/**
 * Derafu: Renderer - Unified Template Rendering Made Simple For PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Renderer\Formatter\Handler;

use Closure;
use Derafu\Translation\TranslatableMessage;
use Stringable;
use Throwable;

/**
 * Generic handler for formatting data values.
 *
 * This handler provides a flexible way to format values based on different
 * handler types:
 *
 *   - String handlers use sprintf format.
 *   - Array handlers use key-value mapping.
 *   - Closure handlers allow custom formatting logic.
 *   - Object handlers with 'find' method are treated as repositories.
 *
 * If no specific handling is possible, the value is cast to string using best
 * effort through the cast() method.
 *
 * @example Using string handler (sprintf):
 * ```php
 * $handler->handle(42, 'Value is: %d'); // "Value is: 42"
 * ```
 *
 * @example Using array handler (mapping):
 * ```php
 * $handler->handle('key', ['key' => 'value']); // "value"
 * ```
 *
 * @example Using closure handler:
 * ```php
 * $handler->handle(42, fn($v) => $v * 2); // "84"
 * ```
 *
 * @example Using object handler with find():
 * ```php
 * $repository = new UserRepository();
 * $handler->handle(1, $repository); // User::toString()
 * ```
 */
class GenericFormatterHandler
{
    /**
     * Handles the formatting of a value according to the provided handler.
     *
     * @param mixed $value The value to format.
     * @param string|array<mixed>|object|null $handler Handler to use for formatting:
     *   - string: Used as sprintf format.
     *   - array: Used as value mapping.
     *   - Closure: Called with ($value, $format).
     *   - object: Must have find() method.
     *   - null: Direct casting attempted.
     * @param string|null $format The format identifier (used in some handlers).
     * @return string The formatted value.
     */
    public function handle(
        mixed $value,
        string|array|object|null $handler = null,
        ?string $format = null
    ): string {
        // If handler is a string, use it as a sprintf mask. A value that is not
        // a scalar or `null` (an array, an object) is its text, as without a
        // handler.
        if (is_string($handler)) {
            return sprintf($handler, is_scalar($value) || $value === null ? $value : $this->cast($value));
        }

        // If handler is an array, use it as a value mapping.
        // If value not found in map, return original value as string.
        elseif (is_array($handler)) {
            // Only a scalar, `null` or a value that is text can be a key of the
            // map: any other value is not in it.
            if (is_scalar($value) || $value === null || $value instanceof Stringable) {
                return $this->cast($handler[(string) $value] ?? $value);
            }

            return $this->cast($value);
        }

        // If Handler is an object, maybe is a Closure or a "repository".
        elseif (is_object($handler)) {
            // If handler is a Closure, call it directly.
            if ($handler instanceof Closure) {
                return $this->cast($handler($value, $format));
            }
            // If handler is an object with find(), treat as repository.
            //
            // It is `method_exists()` and not an interface on purpose: the
            // repositories are objects of the applications, with a `find()` that
            // they already have and that this package does not own, so an
            // interface of this package would force them to depend on it and to
            // be adapted. Only the name of the method is the contract.
            elseif (method_exists($handler, 'find')) {
                $result = $handler->find($value);
                return $this->cast($result);
            }
        }

        // If no handler matched or handler is null, try direct casting
        return $this->cast($value);
    }

    /**
     * Attempts to cast a value to string using the most appropriate method.
     *
     * The casting priority is:
     *
     *   1. Direct string cast for scalar values and `null`.
     *   2. Stringable objects.
     *   3. JSON encoding for arrays and objects.
     *   4. Error message if all else fails (not as exception, as a string).
     *
     * @param mixed $value The value to cast to string.
     * @return string The resulting string representation.
     */
    private function cast(mixed $value): string
    {
        // Direct cast for scalar values and null.
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        // Use the text of an object that can be a string.
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        // Try JSON encoding for arrays and objects.
        try {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            // The text of the error is the formatted value (it is not an
            // exception, it does not stop the rendering).
            //
            // LIMITATION: this text is NOT translated when it is shown. The
            // message is written to be translatable (English id, parameters, and
            // an entry in the catalogue of the package), but this handler has no
            // translator to translate it with, so `(string)` formats it in
            // English, as it is written. To translate it, the handler would have
            // to receive a translator, or the error would have to be an exception
            // (that would stop the rendering): both are changes of behavior or of
            // the API of the package, so they are not made here.
            //
            // The text of the error (`$e->getMessage()`) comes from PHP, or from
            // the object that failed: it is never translated, it goes as a
            // parameter inside the sentence of this package.
            return (string) new TranslatableMessage(
                'Serialization for data type {type} failed: {message}',
                ['type' => get_debug_type($value), 'message' => $e->getMessage()],
                'errors'
            );
        }
    }
}
