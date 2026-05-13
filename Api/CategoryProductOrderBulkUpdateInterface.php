<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Api;

interface CategoryProductOrderBulkUpdateInterface
{
    /**
     * @param int $category_id
     * @param int $store_id
     * @param \LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderBulkItemInterface[] $items
     * @return \LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderUpdateResponseInterface
     */
    public function execute(
        int $category_id,
        int $store_id,
        array $items
    );
}