<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Model\Service;

use LeanCommerce\CategoryProductOrderApi\Api\CategoryProductOrderBulkUpdateInterface;
use LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderUpdateResponseInterfaceFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Psr\Log\LoggerInterface;

class BulkUpdateCategoryProductOrder implements CategoryProductOrderBulkUpdateInterface
{
    private const VM_TABLE = 'smile_virtualcategory_catalog_category_product_position';
    private const ENTITY_TYPE_CATALOG_PRODUCT = 4;
    private const STATUS_ENABLED = 1;
    private const VISIBILITY_CATALOG = 2;
    private const VISIBILITY_SEARCH = 3;
    private const VISIBILITY_BOTH = 4;

    private ResourceConnection $resource;
    private CategoryRepositoryInterface $categoryRepository;
    private CategoryResource $categoryResource;
    private CategoryProductOrderUpdateResponseInterfaceFactory $responseFactory;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resource,
        CategoryRepositoryInterface $categoryRepository,
        CategoryResource $categoryResource,
        CategoryProductOrderUpdateResponseInterfaceFactory $responseFactory,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->categoryRepository = $categoryRepository;
        $this->categoryResource = $categoryResource;
        $this->responseFactory = $responseFactory;
        $this->logger = $logger;
    }

    public function execute(
        int $category_id,
        int $store_id,
        array $items
    ) {
        if ($category_id <= 0 || empty($items) || $store_id < 0) {
            throw new WebapiException(
                __('Invalid payload. category_id and items are required.'),
                0,
                422
            );
        }

        try {
            $category = $this->categoryRepository->get($category_id, $store_id);
        } catch (NoSuchEntityException $exception) {
            throw new WebapiException(__('Category with id "%1" does not exist.', $category_id), 0, 404);
        }

        $normalizedItems = $this->normalizeItems($items);
        $requestedSkus = array_column($normalizedItems, 'sku');

        $connection = $this->resource->getConnection();

        $productTable = $this->resource->getTableName('catalog_product_entity');
        $productIntTable = $this->resource->getTableName('catalog_product_entity_int');
        $attributeTable = $this->resource->getTableName('eav_attribute');
        $categoryProductTable = $this->resource->getTableName('catalog_category_product');
        $vmTable = $this->resource->getTableName(self::VM_TABLE);

        $products = $this->getProductsBySkus($requestedSkus, $productTable);
        $currentPositions = $this->categoryResource->getProductsPosition($category);

        $enabledVisibleProductIds = $this->getEnabledVisibleProductIds(
            $productTable,
            $productIntTable,
            $attributeTable,
            $store_id
        );

        $currentPositions = array_filter(
            $currentPositions,
            static function ($productId) use ($enabledVisibleProductIds): bool {
                return in_array((int)$productId, $enabledVisibleProductIds, true);
            },
            ARRAY_FILTER_USE_KEY
        );

        $currentProductIds = array_map('intval', array_keys($currentPositions));

        $validItems = [];
        $skipped = [];

        foreach ($normalizedItems as $item) {
            $sku = $item['sku'];

            if (!isset($products[$sku])) {
                $skipped[] = [
                    'sku' => $sku,
                    'position' => $item['position'],
                    'reason' => 'SKU not found'
                ];
                continue;
            }

            $productId = (int)$products[$sku];

            if (!in_array($productId, $currentProductIds, true)) {
                $skipped[] = [
                    'sku' => $sku,
                    'position' => $item['position'],
                    'reason' => 'Product disabled, not visible, or does not belong to category'
                ];
                continue;
            }

            $validItems[] = $item;
        }

        if (empty($validItems)) {
            $response = $this->responseFactory->create();
            $response->setCategoryId($category_id);
            $response->setSku('BULK');
            $response->setRequestedPosition(count($normalizedItems));
            $response->setAppliedPositionSource($connection->isTableExists($vmTable) ? 'visual_merchandiser' : 'native');
            $response->setAdminPosition(0);
            $response->setFrontendPosition(null);
            $response->setSuccess(false);
            $response->setUpdatedCount(0);
            $response->setSkippedCount(count($skipped));
            $response->setUpdatedSkus([]);
            $response->setSkipped($skipped);
            $response->setMessage('No products were updated. All requested SKUs were skipped.');

            return $response;
        }

        asort($currentPositions, SORT_NUMERIC);

        $orderedProductIds = array_map('intval', array_keys($currentPositions));
        $movingProductIds = [];

        foreach ($validItems as $item) {
            $movingProductIds[] = (int)$products[$item['sku']];
        }

        $orderedProductIds = array_values(array_filter(
            $orderedProductIds,
            static function (int $productId) use ($movingProductIds): bool {
                return !in_array($productId, $movingProductIds, true);
            }
        ));

        usort($validItems, static function (array $a, array $b): int {
            return $a['position'] <=> $b['position'];
        });

        foreach ($validItems as $item) {
            $productId = (int)$products[$item['sku']];
            $index = max(0, (int)$item['position'] - 1);
            array_splice($orderedProductIds, $index, 0, [$productId]);
        }

        $newPositions = [];

        foreach ($orderedProductIds as $index => $productId) {
            $newPositions[(int)$productId] = $index + 1;
        }

        $nativeRows = [];

        foreach ($newPositions as $productId => $position) {
            $nativeRows[] = [
                'category_id' => $category_id,
                'product_id' => $productId,
                'position' => $position,
            ];
        }

        $connection->beginTransaction();

        try {
            if (!empty($nativeRows)) {
                $connection->insertOnDuplicate(
                    $categoryProductTable,
                    $nativeRows,
                    ['position']
                );
            }

            if ($connection->isTableExists($vmTable)) {
                $vmRows = [];

                foreach ($newPositions as $productId => $position) {
                    $vmRows[] = [
                        'category_id' => $category_id,
                        'product_id' => $productId,
                        'store_id' => $store_id,
                        'position' => $position,
                        'is_blacklisted' => 0,
                    ];
                }

                if (!empty($vmRows)) {
                    $connection->insertOnDuplicate(
                        $vmTable,
                        $vmRows,
                        ['position', 'is_blacklisted']
                    );
                }
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            $this->logger->error('CategoryProductOrderApi bulk partial update failed.', [
                'category_id' => $category_id,
                'store_id' => $store_id,
                'valid_items' => $validItems,
                'skipped' => $skipped,
                'exception' => $exception,
            ]);

            throw new WebapiException(__('Unable to update product positions.'), 0, 500);
        }

        $updatedSkus = array_column($validItems, 'sku');

        $response = $this->responseFactory->create();
        $response->setCategoryId($category_id);
        $response->setSku('BULK');
        $response->setRequestedPosition(count($normalizedItems));
        $response->setAppliedPositionSource($connection->isTableExists($vmTable) ? 'visual_merchandiser' : 'native');
        $response->setAdminPosition(count($validItems));
        $response->setFrontendPosition(null);
        $response->setSuccess(true);
        $response->setUpdatedCount(count($validItems));
        $response->setSkippedCount(count($skipped));
        $response->setUpdatedSkus($updatedSkus);
        $response->setSkipped($skipped);
        $response->setMessage(
            sprintf(
                'Bulk partial update completed. %s product(s) updated, %s product(s) skipped. Reindex was not executed synchronously.',
                count($validItems),
                count($skipped)
            )
        );

        return $response;
    }

    private function getProductsBySkus(array $skus, string $productTable): array
    {
        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from($productTable, ['sku', 'entity_id']);

        $conditions = [];

        foreach ($skus as $sku) {
            $conditions[] = $connection->quoteInto('TRIM(sku) = ?', trim((string)$sku));
        }

        $select->where(implode(' OR ', $conditions));

        $rows = $connection->fetchAll($select);
        $products = [];

        foreach ($rows as $row) {
            $products[trim((string)$row['sku'])] = (int)$row['entity_id'];
        }

        return $products;
    }

    private function getEnabledVisibleProductIds(
        string $productTable,
        string $productIntTable,
        string $attributeTable,
        int $storeId
    ): array {
        $connection = $this->resource->getConnection();

        $statusAttributeId = (int)$connection->fetchOne(
            $connection->select()
                ->from($attributeTable, ['attribute_id'])
                ->where('attribute_code = ?', 'status')
                ->where('entity_type_id = ?', self::ENTITY_TYPE_CATALOG_PRODUCT)
        );

        $visibilityAttributeId = (int)$connection->fetchOne(
            $connection->select()
                ->from($attributeTable, ['attribute_id'])
                ->where('attribute_code = ?', 'visibility')
                ->where('entity_type_id = ?', self::ENTITY_TYPE_CATALOG_PRODUCT)
        );

        $select = $connection->select()
            ->from(['cpe' => $productTable], ['entity_id'])
            ->joinLeft(
                ['status_default' => $productIntTable],
                'status_default.entity_id = cpe.entity_id
                AND status_default.attribute_id = ' . $statusAttributeId . '
                AND status_default.store_id = 0',
                []
            )
            ->joinLeft(
                ['status_store' => $productIntTable],
                'status_store.entity_id = cpe.entity_id
                AND status_store.attribute_id = ' . $statusAttributeId . '
                AND status_store.store_id = ' . (int)$storeId,
                []
            )
            ->joinLeft(
                ['visibility_default' => $productIntTable],
                'visibility_default.entity_id = cpe.entity_id
                AND visibility_default.attribute_id = ' . $visibilityAttributeId . '
                AND visibility_default.store_id = 0',
                []
            )
            ->joinLeft(
                ['visibility_store' => $productIntTable],
                'visibility_store.entity_id = cpe.entity_id
                AND visibility_store.attribute_id = ' . $visibilityAttributeId . '
                AND visibility_store.store_id = ' . (int)$storeId,
                []
            )
            ->where(
                'COALESCE(status_store.value, status_default.value) = ?',
                self::STATUS_ENABLED
            )
            ->where(
                'COALESCE(visibility_store.value, visibility_default.value) IN (?)',
                [
                    self::VISIBILITY_CATALOG,
                    self::VISIBILITY_SEARCH,
                    self::VISIBILITY_BOTH
                ]
            );

        return array_map('intval', $connection->fetchCol($select));
    }

    private function normalizeItems(array $items): array
    {
        $normalized = [];
        $usedSkus = [];
        $usedPositions = [];

        foreach ($items as $item) {
            if (is_object($item) && method_exists($item, 'getSku') && method_exists($item, 'getPosition')) {
                $sku = trim((string)$item->getSku());
                $position = (int)$item->getPosition();
            } elseif (is_array($item)) {
                $sku = isset($item['sku']) ? trim((string)$item['sku']) : '';
                $position = isset($item['position']) ? (int)$item['position'] : 0;
            } else {
                throw new WebapiException(__('Invalid item format.'), 0, 422);
            }

            if ($sku === '' || $position <= 0) {
                throw new WebapiException(
                    __('Each item requires sku and position greater than zero.'),
                    0,
                    422
                );
            }

            if (isset($usedSkus[$sku])) {
                throw new WebapiException(__('Duplicated SKU in request: %1', $sku), 0, 422);
            }

            if (isset($usedPositions[$position])) {
                throw new WebapiException(__('Duplicated position in request: %1', $position), 0, 422);
            }

            $usedSkus[$sku] = true;
            $usedPositions[$position] = true;

            $normalized[] = [
                'sku' => $sku,
                'position' => $position,
            ];
        }

        return $normalized;
    }
}