<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Api\Data;

interface CategoryProductOrderUpdateResponseInterface
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
     * @return string
     */
    public function getSku();

    /**
     * @param string $sku
     * @return $this
     */
    public function setSku($sku);

    /**
     * @return int
     */
    public function getRequestedPosition();

    /**
     * @param int $requestedPosition
     * @return $this
     */
    public function setRequestedPosition($requestedPosition);

    /**
     * @return string
     */
    public function getAppliedPositionSource();

    /**
     * @param string $source
     * @return $this
     */
    public function setAppliedPositionSource($source);

    /**
     * @return int
     */
    public function getAdminPosition();

    /**
     * @param int $adminPosition
     * @return $this
     */
    public function setAdminPosition($adminPosition);

    /**
     * @return int|null
     */
    public function getFrontendPosition();

    /**
     * @param int|null $frontendPosition
     * @return $this
     */
    public function setFrontendPosition($frontendPosition);

    /**
     * @return string
     */
    public function getMessage();

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage($message);

    /**
     * @return bool
     */
    public function getSuccess();

    /**
     * @param bool $success
     * @return $this
     */
    public function setSuccess($success);

    /**
     * @return int
     */
    public function getUpdatedCount();

    /**
     * @param int $updatedCount
     * @return $this
     */
    public function setUpdatedCount($updatedCount);

    /**
     * @return int
     */
    public function getSkippedCount();

    /**
     * @param int $skippedCount
     * @return $this
     */
    public function setSkippedCount($skippedCount);

    /**
     * @return string[]
     */
    public function getUpdatedSkus();

    /**
     * @param string[] $updatedSkus
     * @return $this
     */
    public function setUpdatedSkus(array $updatedSkus);

    /**
     * @return mixed[]
     */
    public function getSkipped();

    /**
     * @param mixed[] $skipped
     * @return $this
     */
    public function setSkipped(array $skipped);
}