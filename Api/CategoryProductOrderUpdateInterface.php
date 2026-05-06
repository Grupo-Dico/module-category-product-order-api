<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Api;

interface CategoryProductOrderUpdateInterface
{
    /**
     * Update a product position inside a category.
     *
     *
     * @param int $category_id
     * @param string $sku
     * @param int $target_position
     * @param int $store_id
     * @return \LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderUpdateResponseInterface
     */
    public function execute(
        int $category_id,
        string $sku,
        int $target_position,
        int $store_id = 0
    );
}
