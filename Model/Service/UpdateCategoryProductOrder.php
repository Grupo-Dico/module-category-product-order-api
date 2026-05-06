<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Model\Service;

use LeanCommerce\CategoryProductOrderApi\Api\CategoryProductOrderUpdateInterface;
use LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderUpdateResponseInterfaceFactory;
use LeanCommerce\CategoryProductOrderApi\Model\Position\NativeCategoryPositionUpdater;
use LeanCommerce\CategoryProductOrderApi\Model\Position\PositionEngineResolver;
use LeanCommerce\CategoryProductOrderApi\Model\Position\VisualMerchandiserPositionUpdater;
use LeanCommerce\CategoryProductOrderApi\Model\Search\ElasticsuiteCategoryProductProvider;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Webapi\Exception as WebapiException;
use Psr\Log\LoggerInterface;

class UpdateCategoryProductOrder implements CategoryProductOrderUpdateInterface
{
    private ProductRepositoryInterface $productRepository;
    private CategoryRepositoryInterface $categoryRepository;
    private PositionEngineResolver $resolver;
    private NativeCategoryPositionUpdater $nativeUpdater;
    private VisualMerchandiserPositionUpdater $vmUpdater;
    private ElasticsuiteCategoryProductProvider $elasticProvider;
    private CategoryProductOrderUpdateResponseInterfaceFactory $responseFactory;
    private IndexerRegistry $indexerRegistry;
    private LoggerInterface $logger;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        PositionEngineResolver $resolver,
        NativeCategoryPositionUpdater $nativeUpdater,
        VisualMerchandiserPositionUpdater $vmUpdater,
        ElasticsuiteCategoryProductProvider $elasticProvider,
        CategoryProductOrderUpdateResponseInterfaceFactory $responseFactory,
        IndexerRegistry $indexerRegistry,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->resolver = $resolver;
        $this->nativeUpdater = $nativeUpdater;
        $this->vmUpdater = $vmUpdater;
        $this->elasticProvider = $elasticProvider;
        $this->responseFactory = $responseFactory;
        $this->indexerRegistry = $indexerRegistry;
        $this->logger = $logger;
    }

    public function execute(
        int $category_id,
        string $sku,
        int $target_position,
        int $store_id = 0
    ) {
        $sku = trim($sku);

        if ($category_id <= 0 || $sku === '' || $target_position <= 0 || $store_id < 0) {
            throw new WebapiException(
                __('Invalid payload. category_id, sku and target_position are required. target_position must be greater than zero.'),
                0,
                422
            );
        }

        try {
            $category = $this->categoryRepository->get($category_id, $store_id);
        } catch (NoSuchEntityException $exception) {
            throw new WebapiException(__('Category with id "%1" does not exist.', $category_id), 0, 404);
        }

        try {
            $product = $this->productRepository->get($sku, false, $store_id);
        } catch (NoSuchEntityException $exception) {
            throw new WebapiException(__('Product with sku "%1" does not exist.', $sku), 0, 404);
        }

        $engine = $this->resolver->resolve($category);

        try {
            $adminPosition = $engine === PositionEngineResolver::ENGINE_VM
                ? $this->vmUpdater->update($category, $product, $target_position, $store_id)
                : $this->nativeUpdater->update($category, $product, $target_position);

            $this->reindex((int) $category_id, (int) $product->getId());

            $frontendPosition = $this->resolveFrontendPosition($category, $sku, $store_id);
        } catch (LocalizedException $exception) {
            $this->logger->warning('CategoryProductOrderApi detected an unsupported merchandising update.', [
                'category_id' => $category_id,
                'sku' => $sku,
                'target_position' => $target_position,
                'store_id' => $store_id,
                'engine' => $engine,
                'exception' => $exception,
            ]);

            throw new WebapiException($exception->getPhrase(), 0, 409);
        } catch (WebapiException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('CategoryProductOrderApi failed while updating product category position.', [
                'category_id' => $category_id,
                'sku' => $sku,
                'target_position' => $target_position,
                'store_id' => $store_id,
                'engine' => $engine,
                'exception' => $exception,
            ]);

            throw new WebapiException(__('Unable to update product position at this time.'), 0, 500);
        }

        $response = $this->responseFactory->create();
        $response->setCategoryId($category_id);
        $response->setSku($sku);
        $response->setRequestedPosition($target_position);
        $response->setAppliedPositionSource($engine);
        $response->setAdminPosition($adminPosition);
        $response->setFrontendPosition($frontendPosition);
        $response->setMessage($frontendPosition === $adminPosition ? 'Position updated' : 'Position updated; frontend index may still be refreshing');

        return $response;
    }

    private function resolveFrontendPosition($category, string $sku, int $storeId): ?int
    {
        try {
            return $this->elasticProvider->getProductPosition($category, $sku, $storeId);
        } catch (\Throwable $exception) {
            $this->logger->warning('CategoryProductOrderApi could not resolve frontend position after update.', [
                'category_id' => (int) $category->getId(),
                'sku' => $sku,
                'store_id' => $storeId,
                'exception' => $exception,
            ]);
            return null;
        }
    }

    private function reindex(int $categoryId, int $productId): void
    {
        foreach ([
            'catalog_category_product' => [$categoryId],
            'catalogsearch_fulltext' => [$productId],
        ] as $indexerId => $ids) {
            try {
                $this->indexerRegistry->get($indexerId)->reindexList($ids);
            } catch (\Throwable $exception) {
                $this->logger->warning('CategoryProductOrderApi could not complete synchronous reindex.', [
                    'indexer_id' => $indexerId,
                    'category_id' => $categoryId,
                    'product_id' => $productId,
                    'exception' => $exception,
                ]);
            }
        }
    }
}
