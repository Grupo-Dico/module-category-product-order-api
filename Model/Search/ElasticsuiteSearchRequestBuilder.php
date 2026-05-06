<?php

namespace LeanCommerce\CategoryProductOrderApi\Model\Search;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterfaceFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Search\RequestInterface;
use Smile\ElasticsuiteCore\Model\Search\RequestBuilder;

class ElasticsuiteSearchRequestBuilder
{
    private const SEARCH_CONTAINER = 'catalog_view_container';
    private const CATEGORY_FILTER = 'category_ids';

    private RequestBuilder $requestBuilder;
    private SearchCriteriaInterfaceFactory $searchCriteriaFactory;
    private FilterBuilder $filterBuilder;
    private FilterGroupBuilder $filterGroupBuilder;
    private SortOrderBuilder $sortOrderBuilder;
    private SearchContextStateManager $searchContextStateManager;

    public function __construct(
        RequestBuilder $requestBuilder,
        SearchCriteriaInterfaceFactory $searchCriteriaFactory,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        SortOrderBuilder $sortOrderBuilder,
        SearchContextStateManager $searchContextStateManager
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->searchCriteriaFactory = $searchCriteriaFactory;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchContextStateManager = $searchContextStateManager;
    }

    public function build(
        CategoryInterface $category,
        int $page,
        int $pageSize,
        string $order,
        string $dir
    ): RequestInterface {
        /** @var SearchCriteriaInterface $searchCriteria */
        $searchCriteria = $this->searchCriteriaFactory->create();

        $searchCriteria->setRequestName(self::SEARCH_CONTAINER);
        $searchCriteria->setCurrentPage(max(1, $page));
        $searchCriteria->setPageSize(max(1, $pageSize));

        $this->addFilter(
            $searchCriteria,
            self::CATEGORY_FILTER,
            (int) $category->getId(),
            'eq'
        );

        if ($order !== '') {
            $searchCriteria->setSortOrders([
                $this->sortOrderBuilder
                    ->setField($order)
                    ->setDirection(
                        strtolower($dir) === strtolower(SortOrder::SORT_DESC)
                            ? SortOrder::SORT_DESC
                            : SortOrder::SORT_ASC
                    )
                    ->create()
            ]);
        }

        $contextSnapshot = $this->searchContextStateManager->snapshot();

        try {
            return $this->requestBuilder->getRequest($searchCriteria);
        } finally {
            $this->searchContextStateManager->restore($contextSnapshot);
        }
    }

    private function addFilter(
        SearchCriteriaInterface $searchCriteria,
        string $field,
        $value,
        string $conditionType
    ): void {
        $filter = $this->filterBuilder
            ->setField($field)
            ->setConditionType($conditionType)
            ->setValue($value)
            ->create();

        $filterGroups = $searchCriteria->getFilterGroups() ?: [];

        $filterGroups[] = $this->filterGroupBuilder
            ->setFilters([$filter])
            ->create();

        $searchCriteria->setFilterGroups($filterGroups);
    }
}