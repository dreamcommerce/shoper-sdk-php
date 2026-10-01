<?php

namespace Shoper\Sdk\Rest\Specialoffers\Requests;

use Shoper\Sdk\Rest\Core\Json\JsonSerializableType;

class ListSpecialoffersRequest extends JsonSerializableType
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
     * @var ?int $filtersProductId Filter by product identifier. Supports operators: eq, in, not_in.
     */
    public ?int $filtersProductId;

    /**
     * @var ?int $filtersStockId Filter by stock (product variant) identifier. Supports operators: eq, in, not_in.
     */
    public ?int $filtersStockId;

    /**
     * @param array{
     *   limit?: ?int,
     *   page?: ?int,
     *   filtersProductId?: ?int,
     *   filtersStockId?: ?int,
     * } $values
     */
    public function __construct(
        array $values = [],
    ) {
        $this->limit = $values['limit'] ?? null;
        $this->page = $values['page'] ?? null;
        $this->filtersProductId = $values['filtersProductId'] ?? null;
        $this->filtersStockId = $values['filtersStockId'] ?? null;
    }
}
