<?php

namespace LeanCommerce\CategoryProductOrderApi\Api\Data;

interface CategoryProductOrderResponseInterface
{
    /**
     * @return int
     */
    public function getCategoryId();

    /**
     * @param int $categoryId
     * @return $this
     */
    public function setCategoryId($categoryId);

    /**
     * @return int
     */
    public function getTotal();

    /**
     * @param int $total
     * @return $this
     */
    public function setTotal($total);

    /**
     * @return \LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderItemInterface[]
     */
    public function getItems();

    /**
     * @param \LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderItemInterface[] $items
     * @return $this
     */
    public function setItems($items);
}
