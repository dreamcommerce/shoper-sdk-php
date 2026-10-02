<?php

namespace Shoper\Sdk\Rest\ProductImages\Requests;

use Shoper\Sdk\Rest\Core\Json\JsonSerializableType;
use Shoper\Sdk\Rest\Core\Json\JsonProperty;
use Shoper\Sdk\Rest\ProductImages\Types\ProductImageInsertTranslationsValue;
use Shoper\Sdk\Rest\Core\Types\ArrayType;

class ProductImageInsert extends JsonSerializableType
{
    /**
     * base64-encoded file contents. **Required when `url` is not supplied** - one of the two has to carry the
     * image source.
     *
     * @var ?string $content
     */
    #[JsonProperty('content')]
    public ?string $content;

    /**
     * @var ?bool $hidden is the photo hidden
     */
    #[JsonProperty('hidden')]
    public ?bool $hidden;

    /**
     * [product](#tag/Products) identifier the image is attached to. **Required** and it must point to an existing
     * product.
     *
     * @var int $productId
     */
    #[JsonProperty('product_id')]
    public int $productId;

    /**
     * @var ?array<string, ProductImageInsertTranslationsValue> $translations an associative array with object translations; if you want to filter things - you can skip locale subkey
     */
    #[JsonProperty('translations'), ArrayType(['string' => ProductImageInsertTranslationsValue::class])]
    public ?array $translations;

    /**
     * if present, file contents is being downloaded from specified URL. Supply either `url` or `content`. Sending
     * the key with an empty value is rejected - omit it entirely when you want to upload through `content`.
     *
     * @var ?string $url
     */
    #[JsonProperty('url')]
    public ?string $url;

    /**
     * @param array{
     *   productId: int,
     *   content?: ?string,
     *   hidden?: ?bool,
     *   translations?: ?array<string, ProductImageInsertTranslationsValue>,
     *   url?: ?string,
     * } $values
     */
    public function __construct(
        array $values,
    ) {
        $this->content = $values['content'] ?? null;
        $this->hidden = $values['hidden'] ?? null;
        $this->productId = $values['productId'];
        $this->translations = $values['translations'] ?? null;
        $this->url = $values['url'] ?? null;
    }
}
