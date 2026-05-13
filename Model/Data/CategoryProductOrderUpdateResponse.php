<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Model\Data;

use LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderUpdateResponseInterface;
use Magento\Framework\DataObject;

class CategoryProductOrderUpdateResponse extends DataObject implements CategoryProductOrderUpdateResponseInterface
{
    public function getCategoryId()
    {
        return (int) $this->getData('category_id');
    }

    public function setCategoryId($categoryId)
    {
        return $this->setData('category_id', (int) $categoryId);
    }

    public function getSku()
    {
        return (string) $this->getData('sku');
    }

    public function setSku($sku)
    {
        return $this->setData('sku', (string) $sku);
    }

    public function getRequestedPosition()
    {
        return (int) $this->getData('requested_position');
    }

    public function setRequestedPosition($requestedPosition)
    {
        return $this->setData('requested_position', (int) $requestedPosition);
    }

    public function getAppliedPositionSource()
    {
        return (string) $this->getData('applied_position_source');
    }

    public function setAppliedPositionSource($source)
    {
        return $this->setData('applied_position_source', (string) $source);
    }

    public function getAdminPosition()
    {
        return (int) $this->getData('admin_position');
    }

    public function setAdminPosition($adminPosition)
    {
        return $this->setData('admin_position', (int) $adminPosition);
    }

    public function getFrontendPosition()
    {
        $value = $this->getData('frontend_position');
        return $value === null ? null : (int) $value;
    }

    public function setFrontendPosition($frontendPosition)
    {
        return $this->setData(
            'frontend_position',
            $frontendPosition === null ? null : (int) $frontendPosition
        );
    }

    public function getMessage()
    {
        return (string) $this->getData('message');
    }

    public function setMessage($message)
    {
        return $this->setData('message', (string) $message);
    }

    public function getSuccess()
    {
        return (bool) $this->getData('success');
    }

    public function setSuccess($success)
    {
        return $this->setData('success', (bool) $success);
    }

    public function getUpdatedCount()
    {
        return (int) $this->getData('updated_count');
    }

    public function setUpdatedCount($updatedCount)
    {
        return $this->setData('updated_count', (int) $updatedCount);
    }

    public function getSkippedCount()
    {
        return (int) $this->getData('skipped_count');
    }

    public function setSkippedCount($skippedCount)
    {
        return $this->setData('skipped_count', (int) $skippedCount);
    }

    public function getUpdatedSkus()
    {
        $value = $this->getData('updated_skus');
        return is_array($value) ? $value : [];
    }

    public function setUpdatedSkus(array $updatedSkus)
    {
        return $this->setData('updated_skus', $updatedSkus);
    }

    public function getSkipped()
    {
        $value = $this->getData('skipped');
        return is_array($value) ? $value : [];
    }

    public function setSkipped(array $skipped)
    {
        return $this->setData('skipped', $skipped);
    }
}