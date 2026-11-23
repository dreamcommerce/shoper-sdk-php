<?php

declare(strict_types=1);

namespace Shoper\Sdk\Tests\Helpers;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shoper\Sdk\Rest\CollectionsProducts\CollectionsProductsClient;
use Shoper\Sdk\Rest\Core\Client\MockHttpClient;
use Shoper\Sdk\Rest\Core\Client\RawClient;
use Shoper\Sdk\Rest\Metafields\MetafieldsClient;
use Shoper\Sdk\Rest\PaymentsChannels\PaymentsChannelsClient;
use Shoper\Sdk\Rest\Products\ProductsClient;

final class GeneratedPathEncodingTest extends TestCase
{
    private MockHttpClient $http;
    private RawClient $raw;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $this->http->append(new Response(200, ['Content-Type' => 'application/json'], '1'));
        $this->raw = new RawClient(['client' => $this->http, 'baseUrl' => 'https://shop.example.com']);
    }

    private function lastPath(): string
    {
        $request = $this->http->getLastRequest();
        self::assertNotNull($request);
        return $request->getUri()->getPath();
    }

    public function test_traversal_identifier_is_sent_as_one_segment(): void
    {
        try {
            (new ProductsClient($this->raw))->get('1/../../users');
        } catch (\Throwable $e) {
        }
        self::assertSame('/webapi/rest/products/1%2F..%2F..%2Fusers', $this->lastPath());
    }

    public function test_update_and_delete_encode_identifier(): void
    {
        $client = new ProductsClient($this->raw);
        foreach (['update', 'delete'] as $method) {
            $this->http->append(new Response(200, ['Content-Type' => 'application/json'], '1'));
            try {
                $method === 'update' ? $client->update('5/../../coupons') : $client->delete('5/../../coupons');
            } catch (\Throwable $e) {
            }
            self::assertSame('/webapi/rest/products/5%2F..%2F..%2Fcoupons', $this->lastPath());
        }
    }

    public function test_legitimate_identifier_is_unchanged(): void
    {
        try {
            (new ProductsClient($this->raw))->get('123');
        } catch (\Throwable $e) {
        }
        self::assertSame('/webapi/rest/products/123', $this->lastPath());
    }

    public function test_multi_parameter_path_is_unchanged_for_legitimate_values(): void
    {
        try {
            (new CollectionsProductsClient($this->raw))->update('7', '9');
        } catch (\Throwable $e) {
        }
        self::assertSame('/webapi/rest/collections/7/products/9', $this->lastPath());
    }

    public function test_dot_dot_payment_id_is_rejected_before_request(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        try {
            (new PaymentsChannelsClient($this->raw))->get('..', '5');
        } finally {
            self::assertSame(0, $this->http->getRequestCount());
        }
    }

    public function test_dot_dot_metafield_object_is_rejected_before_request(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        try {
            (new MetafieldsClient($this->raw))->get('..', '5');
        } finally {
            self::assertSame(0, $this->http->getRequestCount());
        }
    }

    public function test_generated_clients_have_no_interpolated_paths(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src'));
        $encodeCalls = 0;
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (preg_match('/path: "[^"]*\{\$/', $code)) {
                $offenders[] = $file->getPathname();
            }
            if (str_contains($file->getPathname(), '/src/Rest/')) {
                $encodeCalls += substr_count($code, 'PathSegment::encode(');
            }
        }
        self::assertSame([], $offenders);
        self::assertGreaterThan(0, $encodeCalls);
    }
}
