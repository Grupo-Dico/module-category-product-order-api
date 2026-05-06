<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Model\Position;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Framework\Exception\LocalizedException;

class NativeCategoryPositionUpdater
{
    private CategoryResource $categoryResource;

    public function __construct(CategoryResource $categoryResource)
    {
        $this->categoryResource = $categoryResource;
    }

    public function update(Category $category, ProductInterface $product, int $position): int
    {
        $productId = (int) $product->getId();
        $positions = $this->categoryResource->getProductsPosition($category);

        if (!array_key_exists($productId, $positions)) {
            throw new LocalizedException(__('Product does not belong to this category.'));
        }

        asort($positions, SORT_NUMERIC);
        $orderedProductIds = array_map('intval', array_keys($positions));
        $orderedProductIds = array_values(array_filter(
            $orderedProductIds,
            static fn (int $id): bool => $id !== $productId
        ));

        array_splice($orderedProductIds, max(0, $position - 1), 0, [$productId]);

        $newPositions = [];
        foreach ($orderedProductIds as $index => $id) {
            $newPositions[$id] = $index + 1;
        }

        $category->setPostedProducts($newPositions);
        $this->categoryResource->save($category);

        return (int) $newPositions[$productId];
    }
}
