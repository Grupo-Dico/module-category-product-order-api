<?php

namespace LeanCommerce\CategoryProductOrderApi\Api;

interface CategoryProductOrderInterface
{
    /**
     * @param int $categoryId
     * @param int $storeId
     * @return \LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderResponseInterface
     */
    public function execute($categoryId, $storeId = 0);
}