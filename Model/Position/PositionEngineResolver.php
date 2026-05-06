<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Model\Position;

use Magento\Catalog\Model\Category;
use Magento\Framework\App\ResourceConnection;

class PositionEngineResolver
{
    public const ENGINE_VM = 'visual_merchandiser';
    public const ENGINE_NATIVE = 'magento_native';

    private const TABLE_NAME = 'smile_virtualcategory_catalog_category_product_position';
    private const VIRTUAL_CATEGORY_ATTRIBUTE = 'is_virtual_category';

    private ResourceConnection $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function resolve(Category $category): string
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_NAME);

        if (!$connection->isTableExists($table)) {
            return self::ENGINE_NATIVE;
        }

        if ((bool) $category->getData(self::VIRTUAL_CATEGORY_ATTRIBUTE)) {
            return self::ENGINE_VM;
        }

        $hasVmState = (bool) $connection->fetchOne(
            $connection->select()
                ->from($table, ['cnt' => 'COUNT(*)'])
                ->where('category_id = ?', (int) $category->getId())
                ->limit(1)
        );

        return $hasVmState ? self::ENGINE_VM : self::ENGINE_NATIVE;
    }
}
