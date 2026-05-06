<?php

namespace LeanCommerce\CategoryProductOrderApi\Model;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Config as CatalogConfig;


class CategorySortResolver
{
    private CatalogConfig $catalogConfig;

    public function __construct(CatalogConfig $catalogConfig)
    {
        $this->catalogConfig = $catalogConfig;
    }

    /**
     * @return array{order:string,dir:string}
     */
    public function resolve(
        CategoryInterface $category,
        ?string $requestedOrder = null,
        ?string $requestedDirection = null
    ): array {
        $availableOrders = $category->getAvailableSortByOptions() ?: [];

        if (empty($availableOrders)) {
            $availableOrders = $this->catalogConfig->getAttributeUsedForSortByArray();
        }

        $defaultOrder = (string) ($category->getDefaultSortBy() ?: $this->catalogConfig->getProductListDefaultSortBy());
        if ($defaultOrder === '' || !isset($availableOrders[$defaultOrder])) {
            $keys = array_keys($availableOrders);
            $defaultOrder = (string) reset($keys);
        }

        $order = $requestedOrder && isset($availableOrders[$requestedOrder]) ? $requestedOrder : $defaultOrder;
        $dir = strtolower((string) $requestedDirection);
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';

        return [
            'order' => $order,
            'dir' => $dir,
        ];
    }
}
