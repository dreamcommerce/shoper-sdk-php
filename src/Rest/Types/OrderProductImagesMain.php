<?php

namespace Shoper\Sdk\Rest\Types;

use Shoper\Sdk\Rest\Core\Json\JsonSerializableType;
use Shoper\Sdk\Rest\Core\Json\JsonProperty;

/**
 * Full-size stock image.
 */
class OrderProductImagesMain extends JsonSerializableType
{
    /**
     * @var ?bool $isPlaceholder Whether the URL points to the placeholder image because the stock has no image.
     */
    #[JsonProperty('is_placeholder')]
    public ?bool $isPlaceholder;

    /**
     * @var ?string $url Absolute URL of the full-size image.
     */
    #[JsonProperty('url')]
    public ?string $url;

    /**
     * @param array{
     *   isPlaceholder?: ?bool,
     *   url?: ?string,
     * } $values
     */
    public function __construct(
        array $values = [],
    ) {
        $this->isPlaceholder = $values['isPlaceholder'] ?? null;
        $this->url = $values['url'] ?? null;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
