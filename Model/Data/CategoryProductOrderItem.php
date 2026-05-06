<?php

namespace LeanCommerce\CategoryProductOrderApi\Model\Data;

use Magento\Framework\DataObject;
use LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderItemInterface;

class CategoryProductOrderItem extends DataObject implements CategoryProductOrderItemInterface
{
    public function getSku()
    {
        return (string) $this->getData('sku');
    }

    public function setSku($sku)
    {
        return $this->setData('sku', (string) $sku);
    }

    public function getName()
    {
        return (string) $this->getData('name');
    }

    public function setName($name)
    {
        return $this->setData('name', (string) $name);
    }

    public function getPosition()
    {
        return (int) $this->getData('position');
    }

    public function setPosition($position)
    {
        return $this->setData('position', (int) $position);
    }
}
