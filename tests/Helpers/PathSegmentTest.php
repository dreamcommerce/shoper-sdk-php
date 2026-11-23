<?php

declare(strict_types=1);

namespace Shoper\Sdk\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Shoper\Sdk\PathSegment;

final class PathSegmentTest extends TestCase
{
    public function test_encodes_traversal_payload_as_single_segment(): void
    {
        self::assertSame('1%2F..%2F..%2Fusers', PathSegment::encode('1/../../users'));
    }

    public function test_keeps_legitimate_identifiers(): void
    {
        self::assertSame('123', PathSegment::encode('123'));
        self::assertSame('product', PathSegment::encode('product'));
    }

    public function test_encodes_percent_and_reserved_characters(): void
    {
        self::assertSame('%252e%252e', PathSegment::encode('%2e%2e'));
        self::assertSame('a%20b%3Fc%23d', PathSegment::encode('a b?c#d'));
    }

    /**
     * @dataProvider invalidSegments
     */
    public function test_rejects_empty_and_dot_segments(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path parameter: value must be a non-empty string other than "." or ".."');
        PathSegment::encode($value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSegments(): array
    {
        return ['empty' => [''], 'dot' => ['.'], 'dotdot' => ['..']];
    }
}
