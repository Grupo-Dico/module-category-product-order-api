<?php

namespace LeanCommerce\CategoryProductOrderApi\Model\Data;

use Magento\Framework\DataObject;
use LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderResponseInterface;

class CategoryProductOrderResponse extends DataObject implements CategoryProductOrderResponseInterface
{
    public function getCategoryId()
    {
        return (int) $this->getData('category_id');
    }

    public function setCategoryId($categoryId)
    {
        return $this->setData('category_id', (int) $categoryId);
    }

    public function getTotal()
    {
        return (int) $this->getData('total');
    }

    public function setTotal($total)
    {
        return $this->setData('total', (int) $total);
    }

    public function getItems()
    {
        return (array) $this->getData('items');
    }

    public function setItems($items)
    {
        return $this->setData('items', $items);
    }
}
