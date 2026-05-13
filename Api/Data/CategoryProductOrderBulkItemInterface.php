<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Api\Data;

interface CategoryProductOrderBulkItemInterface
{
    /**
     * @return string
     */
    public function getSku(): string;

    /**
     * @param string $sku
     * @return $this
     */
    public function setSku(string $sku);

    /**
     * @return int
     */
    public function getPosition(): int;

    /**
     * @param int $position
     * @return $this
     */
    public function setPosition(int $position);
}