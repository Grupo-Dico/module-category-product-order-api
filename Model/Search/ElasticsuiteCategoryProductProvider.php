<?php

namespace LeanCommerce\CategoryProductOrderApi\Model\Search;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class ElasticsuiteCategoryProductProvider
{
    private const TABLE_NAME = 'smile_virtualcategory_catalog_category_product_position';

    private ResourceConnection $resource;
    private CategoryResource $categoryResource;
    private StoreManagerInterface $storeManager;

    public function __construct(
        ResourceConnection $resource,
        CategoryResource $categoryResource,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
        $this->categoryResource = $categoryResource;
        $this->storeManager = $storeManager;
    }

    public function getOrderedProducts(Category $category, int $storeId = 0): array
    {
        $categoryId = (int) $category->getId();

        if ($categoryId <= 0) {
            throw new LocalizedException(__('Invalid category.'));
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_NAME);
        $productTable = $this->resource->getTableName('catalog_product_entity');

        if (!$connection->isTableExists($table)) {
            return $this->getNativeOrderedProducts($category);
        }

        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['v' => $table], ['position', 'store_id', 'is_blacklisted'])
                ->join(
                    ['p' => $productTable],
                    'p.entity_id = v.product_id',
                    ['entity_id', 'sku']
                )
                ->where('v.category_id = ?', $categoryId)
                ->where('v.store_id = ?', $storeId)
                ->where('v.is_blacklisted = ?', 0)
                ->where('v.position IS NOT NULL')
                ->order('v.position ASC')
        );

        if (!$rows && $storeId !== 0) {
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from(['v' => $table], ['position', 'store_id', 'is_blacklisted'])
                    ->join(
                        ['p' => $productTable],
                        'p.entity_id = v.product_id',
                        ['entity_id', 'sku']
                    )
                    ->where('v.category_id = ?', $categoryId)
                    ->where('v.store_id = ?', 0)
                    ->where('v.is_blacklisted = ?', 0)
                    ->where('v.position IS NOT NULL')
                    ->order('v.position ASC')
            );
        }

        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'product_id' => (int) $row['entity_id'],
                'sku' => (string) $row['sku'],
                'position' => (int) $row['position'],
                'store_id' => (int) $row['store_id'],
            ];
        }

        return [
            'category_id' => $categoryId,
            'total' => count($items),
            'items' => $items,
        ];
    }

    public function getProductPosition(Category $category, string $sku, int $storeId = 0): ?int
    {
        $data = $this->getOrderedProducts($category, $storeId);

        foreach ($data['items'] as $item) {
            if ($item['sku'] === $sku) {
                return (int) $item['position'];
            }
        }

        return null;
    }

    private function getNativeOrderedProducts(Category $category): array
    {
        $positions = $this->categoryResource->getProductsPosition($category);
        asort($positions, SORT_NUMERIC);

        $connection = $this->resource->getConnection();
        $productTable = $this->resource->getTableName('catalog_product_entity');

        $items = [];

        foreach ($positions as $productId => $position) {
            $sku = $connection->fetchOne(
                $connection->select()
                    ->from($productTable, ['sku'])
                    ->where('entity_id = ?', (int) $productId)
            );

            $items[] = [
                'product_id' => (int) $productId,
                'sku' => (string) $sku,
                'position' => (int) $position,
                'store_id' => 0,
            ];
        }

        return [
            'category_id' => (int) $category->getId(),
            'total' => count($items),
            'items' => $items,
        ];
    }
}