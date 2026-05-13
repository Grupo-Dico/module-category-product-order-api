<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Model\Data;

use LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderBulkItemInterface;
use Magento\Framework\DataObject;

class CategoryProductOrderBulkItem extends DataObject implements CategoryProductOrderBulkItemInterface
{
    private const SKU = 'sku';
    private const POSITION = 'position';

    public function getSku(): string
    {
        return (string)$this->getData(self::SKU);
    }

    public function setSku(string $sku)
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getPosition(): int
    {
        return (int)$this->getData(self::POSITION);
    }

    public function setPosition(int $position)
    {
        return $this->setData(self::POSITION, $position);
    }
}