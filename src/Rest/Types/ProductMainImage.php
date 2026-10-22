<?php

namespace Shoper\Sdk\Rest\Types;

use Shoper\Sdk\Rest\Core\Json\JsonSerializableType;
use Shoper\Sdk\Rest\Core\Json\JsonProperty;
use Shoper\Sdk\Rest\Core\Types\ArrayType;

/**
 * an associative array with product main identifier
 */
class ProductMainImage extends JsonSerializableType
{
    /**
     * @var ?string $extension file extension
     */
    #[JsonProperty('extension')]
    public ?string $extension;

    /**
     * @var ?string $gfxId asset identifier
     */
    #[JsonProperty('gfx_id')]
    public ?string $gfxId;

    /**
     * @var ?value-of<ProductMainImageHidden> $hidden is the photo hidden?
     */
    #[JsonProperty('hidden')]
    public ?string $hidden;

    /**
     * @var ?string $name photo description
     */
    #[JsonProperty('name')]
    public ?string $name;

    /**
     * @var ?string $order photo order
     */
    #[JsonProperty('order')]
    public ?string $order;

    /**
     * @var ?array<string, ProductMainImageTranslationsValue> $translations photo translations keyed by locale (for example <code>pl_PL</code>); locales without a translation are omitted
     */
    #[JsonProperty('translations'), ArrayType(['string' => ProductMainImageTranslationsValue::class])]
    public ?array $translations;

    /**
     * @var ?string $unicName unique photo name, pointing to a file in filesystem
     */
    #[JsonProperty('unic_name')]
    public ?string $unicName;

    /**
     * @param array{
     *   extension?: ?string,
     *   gfxId?: ?string,
     *   hidden?: ?value-of<ProductMainImageHidden>,
     *   name?: ?string,
     *   order?: ?string,
     *   translations?: ?array<string, ProductMainImageTranslationsValue>,
     *   unicName?: ?string,
     * } $values
     */
    public function __construct(
        array $values = [],
    ) {
        $this->extension = $values['extension'] ?? null;
        $this->gfxId = $values['gfxId'] ?? null;
        $this->hidden = $values['hidden'] ?? null;
        $this->name = $values['name'] ?? null;
        $this->order = $values['order'] ?? null;
        $this->translations = $values['translations'] ?? null;
        $this->unicName = $values['unicName'] ?? null;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
