<?php

namespace LeanCommerce\CategoryProductOrderApi\Model\Position;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class VisualMerchandiserPositionUpdater
{
    private const TABLE_NAME = 'smile_virtualcategory_catalog_category_product_position';

    private ResourceConnection $resource;
    private NativeCategoryPositionUpdater $nativeUpdater;
    private StoreManagerInterface $storeManager;

    public function __construct(
        ResourceConnection $resource,
        NativeCategoryPositionUpdater $nativeUpdater,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
        $this->nativeUpdater = $nativeUpdater;
        $this->storeManager = $storeManager;
    }

    public function update(Category $category, ProductInterface $product, int $position, int $storeId = 0): int
    {
        $categoryId = (int) $category->getId();
        $productId = (int) $product->getId();

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_NAME);

        if (!$connection->isTableExists($table)) {
            return $this->nativeUpdater->update($category, $product, $position);
        }

        $visiblePositions = $this->getVisibleEnabledProductPositions($categoryId, $storeId);

        if (!isset($visiblePositions[$productId])) {
            throw new LocalizedException(
                __('Product does not belong to this category or is not enabled/visible for this store.')
            );
        }

        asort($visiblePositions, SORT_NUMERIC);

        $orderedProductIds = array_map('intval', array_keys($visiblePositions));

        $orderedProductIds = array_values(array_filter(
            $orderedProductIds,
            static function ($id) use ($productId) {
                return (int) $id !== $productId;
            }
        ));

        array_splice($orderedProductIds, max(0, $position - 1), 0, [$productId]);

        $newPositions = [];

        foreach ($orderedProductIds as $index => $id) {
            $newPositions[(int) $id] = $index + 1;
        }

        $storeIds = $this->getAllStoreIds();

        $connection->beginTransaction();

        try {
            foreach ($storeIds as $storeIdLoop) {
                $connection->delete(
                    $table,
                    [
                        'category_id = ?' => $categoryId,
                        'store_id = ?' => (int) $storeIdLoop
                    ]
                );
            }

            foreach ($newPositions as $id => $newPosition) {
                foreach ($storeIds as $storeIdLoop) {
                    $connection->insert(
                        $table,
                        [
                            'category_id'    => $categoryId,
                            'product_id'     => (int) $id,
                            'store_id'       => (int) $storeIdLoop,
                            'position'       => (int) $newPosition,
                            'is_blacklisted' => 0,
                        ]
                    );
                }
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw new LocalizedException(
                __('Unable to update Visual Merchandiser position.'),
                $exception
            );
        }

        $this->nativeUpdater->update($category, $product, $position);

        return $newPositions[$productId];
    }

    private function getVisibleEnabledProductPositions(int $categoryId, int $storeId): array
    {
        $connection = $this->resource->getConnection();

        $categoryProductTable = $this->resource->getTableName('catalog_category_product');
        $productTable = $this->resource->getTableName('catalog_product_entity');
        $productIntTable = $this->resource->getTableName('catalog_product_entity_int');
        $attributeTable = $this->resource->getTableName('eav_attribute');
        $entityTypeTable = $this->resource->getTableName('eav_entity_type');

        $entityTypeId = (int) $connection->fetchOne(
            $connection->select()
                ->from($entityTypeTable, ['entity_type_id'])
                ->where('entity_type_code = ?', 'catalog_product')
        );

        $statusAttributeId = (int) $connection->fetchOne(
            $connection->select()
                ->from($attributeTable, ['attribute_id'])
                ->where('attribute_code = ?', 'status')
                ->where('entity_type_id = ?', $entityTypeId)
        );

        $visibilityAttributeId = (int) $connection->fetchOne(
            $connection->select()
                ->from($attributeTable, ['attribute_id'])
                ->where('attribute_code = ?', 'visibility')
                ->where('entity_type_id = ?', $entityTypeId)
        );

        $linkField = $this->getProductLinkField();

        $select = $connection->select()
            ->from(['ccp' => $categoryProductTable], ['product_id', 'position'])
            ->join(['cpe' => $productTable], 'cpe.entity_id = ccp.product_id', [])
            ->joinLeft(
                ['status_default' => $productIntTable],
                sprintf(
                    'status_default.%s = cpe.%s AND status_default.attribute_id = %d AND status_default.store_id = 0',
                    $linkField,
                    $linkField,
                    $statusAttributeId
                ),
                []
            )
            ->joinLeft(
                ['status_store' => $productIntTable],
                sprintf(
                    'status_store.%s = cpe.%s AND status_store.attribute_id = %d AND status_store.store_id = %d',
                    $linkField,
                    $linkField,
                    $statusAttributeId,
                    $storeId
                ),
                []
            )
            ->joinLeft(
                ['visibility_default' => $productIntTable],
                sprintf(
                    'visibility_default.%s = cpe.%s AND visibility_default.attribute_id = %d AND visibility_default.store_id = 0',
                    $linkField,
                    $linkField,
                    $visibilityAttributeId
                ),
                []
            )
            ->joinLeft(
                ['visibility_store' => $productIntTable],
                sprintf(
                    'visibility_store.%s = cpe.%s AND visibility_store.attribute_id = %d AND visibility_store.store_id = %d',
                    $linkField,
                    $linkField,
                    $visibilityAttributeId,
                    $storeId
                ),
                []
            )
            ->where('ccp.category_id = ?', $categoryId)
            ->where(
                'COALESCE(status_store.value, status_default.value) = ?',
                Status::STATUS_ENABLED
            )
            ->where(
                'COALESCE(visibility_store.value, visibility_default.value) IN (?)',
                [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH,
                ]
            )
            ->order('ccp.position ASC');

        $rows = $connection->fetchAll($select);

        $positions = [];

        foreach ($rows as $row) {
            $positions[(int) $row['product_id']] = (int) $row['position'];
        }

        return $positions;
    }

    private function getProductLinkField(): string
    {
        $connection = $this->resource->getConnection();
        $productTable = $this->resource->getTableName('catalog_product_entity');

        $columns = $connection->describeTable($productTable);

        return isset($columns['row_id']) ? 'row_id' : 'entity_id';
    }

    private function getAllStoreIds(): array
    {
        $storeIds = [0];

        foreach ($this->storeManager->getStores(false) as $store) {
            $storeIds[] = (int) $store->getId();
        }

        return array_values(array_unique($storeIds));
    }
}
