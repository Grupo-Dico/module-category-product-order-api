<?php

namespace LeanCommerce\CategoryProductOrderApi\Model\Search;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Search\Model\QueryInterface;
use ReflectionClass;
use Smile\ElasticsuiteCore\Api\Search\ContextInterface;


class SearchContextStateManager
{
    private ContextInterface $searchContext;

    public function __construct(ContextInterface $searchContext)
    {
        $this->searchContext = $searchContext;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'category' => $this->searchContext->getCurrentCategory(),
            'search_query' => $this->searchContext->getCurrentSearchQuery(),
            'store_id' => $this->searchContext->getStoreId(),
            'customer_group_id' => $this->searchContext->getCustomerGroupId(),
            'blacklisting_applied' => $this->searchContext->isBlacklistingApplied(),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function restore(array $snapshot): void
    {
        $this->setPrivateProperty('category', $snapshot['category'] ?? null, CategoryInterface::class);
        $this->setPrivateProperty('searchQuery', $snapshot['search_query'] ?? null, QueryInterface::class);
        $this->setPrivateProperty('storeId', $snapshot['store_id'] ?? null, null);
        $this->setPrivateProperty('customerGroupId', $snapshot['customer_group_id'] ?? null, null);
        $this->searchContext->setIsBlacklistingApplied((bool) ($snapshot['blacklisting_applied'] ?? true));
    }

    /**
     * @param mixed $value
     */
    private function setPrivateProperty(string $propertyName, $value, ?string $expectedClass): void
    {
        if ($expectedClass !== null && $value !== null && !$value instanceof $expectedClass) {
            return;
        }

        $reflection = new ReflectionClass($this->searchContext);
        if (!$reflection->hasProperty($propertyName)) {
            return;
        }

        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($this->searchContext, $value);
    }
}
