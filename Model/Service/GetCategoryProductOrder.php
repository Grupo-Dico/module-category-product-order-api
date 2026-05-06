<?php

namespace LeanCommerce\CategoryProductOrderApi\Model\Service;

use LeanCommerce\CategoryProductOrderApi\Api\CategoryProductOrderInterface;
use LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderResponseInterfaceFactory;
use LeanCommerce\CategoryProductOrderApi\Model\Search\ElasticsuiteCategoryProductProvider;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class GetCategoryProductOrder implements CategoryProductOrderInterface
{
    private CategoryRepositoryInterface $categoryRepository;
    private ElasticsuiteCategoryProductProvider $provider;
    private CategoryProductOrderResponseInterfaceFactory $responseFactory;
    private LoggerInterface $logger;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        ElasticsuiteCategoryProductProvider $provider,
        CategoryProductOrderResponseInterfaceFactory $responseFactory,
        LoggerInterface $logger
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->provider = $provider;
        $this->responseFactory = $responseFactory;
        $this->logger = $logger;
    }

    /**
     * @param int $categoryId
     * @param int $storeId
     * @return \LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderResponseInterface
     * @throws LocalizedException
     */
    public function execute($categoryId, $storeId = 0)
    {
        try {
            $category = $this->categoryRepository->get((int) $categoryId, (int) $storeId);
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning(
                'CategoryProductOrderApi received an invalid category id.',
                ['category_id' => $categoryId, 'store_id' => $storeId]
            );

            throw new LocalizedException(
                __('Category with id "%1" does not exist.', $categoryId),
                $exception
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                'CategoryProductOrderApi failed while loading category metadata.',
                [
                    'category_id' => $categoryId,
                    'store_id' => $storeId,
                    'exception' => $exception,
                ]
            );

            throw new LocalizedException(
                __('Unable to load the requested category at this time.'),
                $exception
            );
        }

        try {
            $data = $this->provider->getOrderedProducts($category, (int) $storeId);
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error(
                'CategoryProductOrderApi failed while retrieving ordered category products.',
                [
                    'category_id' => (int) $category->getId(),
                    'store_id' => $storeId,
                    'exception' => $exception,
                ]
            );

            throw new LocalizedException(
                __('Unable to retrieve category products from ElasticSuite at this time.'),
                $exception
            );
        }

        $response = $this->responseFactory->create();
        $response->setCategoryId($data['category_id']);
        $response->setTotal($data['total']);
        $response->setItems($data['items']);

        return $response;
    }
}