<?php

namespace Shoper\Sdk\Rest\News\Requests;

use Shoper\Sdk\Rest\Core\Json\JsonSerializableType;

class ListNewsRequest extends JsonSerializableType
{
    /**
     * @var ?int $limit
     */
    public ?int $limit;

    /**
     * @var ?int $page
     */
    public ?int $page;

    /**
     * @var ?string $filtersTag Filter by news tag. Supports operators: eq, like, not_like.
     */
    public ?string $filtersTag;

    /**
     * @var ?int $filtersTagId Filter by news tag identifier. Supports operators: eq, in, not_in.
     */
    public ?int $filtersTagId;

    /**
     * @var ?int $filtersCategoryId Filter by news category identifier. Supports operators: eq, in, not_in.
     */
    public ?int $filtersCategoryId;

    /**
     * @param array{
     *   limit?: ?int,
     *   page?: ?int,
     *   filtersTag?: ?string,
     *   filtersTagId?: ?int,
     *   filtersCategoryId?: ?int,
     * } $values
     */
    public function __construct(
        array $values = [],
    ) {
        $this->limit = $values['limit'] ?? null;
        $this->page = $values['page'] ?? null;
        $this->filtersTag = $values['filtersTag'] ?? null;
        $this->filtersTagId = $values['filtersTagId'] ?? null;
        $this->filtersCategoryId = $values['filtersCategoryId'] ?? null;
    }
}
