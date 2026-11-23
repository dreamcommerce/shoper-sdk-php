<?php

declare(strict_types=1);

namespace Shoper\Sdk;

/**
 * Encodes a value so that it is always a single URL path segment.
 */
final class PathSegment
{
    /**
     * @throws \InvalidArgumentException when the value is empty, "." or ".."
     */
    public static function encode(string $value): string
    {
        if ($value === '' || $value === '.' || $value === '..') {
            throw new \InvalidArgumentException('Invalid path parameter: value must be a non-empty string other than "." or ".."');
        }

        return rawurlencode($value);
    }
}
