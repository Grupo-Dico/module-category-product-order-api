<?php

namespace LeanCommerce\CategoryProductOrderApi\Model\Search;

use LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderItemInterface;
use LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderItemInterfaceFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Select;
use Zend_Db_Expr;


class SearchResultApplier
{
    private CollectionFactory $collectionFactory;
    private CategoryProductOrderItemInterfaceFactory $itemFactory;

    public function __construct(
        CollectionFactory $collectionFactory,
        CategoryProductOrderItemInterfaceFactory $itemFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->itemFactory = $itemFactory;
    }

    /**
     * @param int[] $documentIds
     * @param int $offset
     * @return \LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderItemInterface[]
     */
    public function buildItems(array $documentIds, int $offset = 0): array
    {
        if ($documentIds === []) {
            return [];
        }

        $collection = $this->buildCollection($documentIds);
        $items = [];
        $position = $offset + 1;

        foreach ($collection as $product) {
            /** @var CategoryProductOrderItemInterface $item */
            $item = $this->itemFactory->create();
            $item->setSku((string) $product->getSku());
            $item->setName((string) $product->getName());
            $item->setPosition($position++);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param int[] $documentIds
     * @return Collection
     */
    private function buildCollection(array $documentIds): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['sku', 'name']);
        $collection->addIdFilter($documentIds);

        $select = $collection->getSelect();
        $select->reset(Select::ORDER);
        $select->order(new Zend_Db_Expr(sprintf('FIELD(e.entity_id,%s)', implode(',', $documentIds))));

        return $collection;
    }
}
