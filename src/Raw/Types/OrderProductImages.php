<?php

namespace Shoper\Sdk\Rest\Types;

use Shoper\Sdk\Rest\Core\Json\JsonSerializableType;
use Shoper\Sdk\Rest\Core\Json\JsonProperty;

/**
 * Image set of the [product stock](#tag/ProductStocks) this line item points to, read from the stock record at
 * the moment of the request. When the stock has no image, both entries point to the store placeholder image and
 * `is_placeholder` is `true`. `null` when the line item is not linked to an existing stock.
 */
class OrderProductImages extends JsonSerializableType
{
    /**
     * @var ?OrderProductImagesMain $main Full-size stock image.
     */
    #[JsonProperty('main')]
    public ?OrderProductImagesMain $main;

    /**
     * @var ?OrderProductImagesThumbnail $thumbnail 75x75 px thumbnail of the stock image.
     */
    #[JsonProperty('thumbnail')]
    public ?OrderProductImagesThumbnail $thumbnail;

    /**
     * @param array{
     *   main?: ?OrderProductImagesMain,
     *   thumbnail?: ?OrderProductImagesThumbnail,
     * } $values
     */
    public function __construct(
        array $values = [],
    ) {
        $this->main = $values['main'] ?? null;
        $this->thumbnail = $values['thumbnail'] ?? null;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
