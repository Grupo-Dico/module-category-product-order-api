<?php

declare(strict_types=1);

namespace LeanCommerce\CategoryProductOrderApi\Model\Service;

use LeanCommerce\CategoryProductOrderApi\Api\CategoryProductOrderUpdateInterface;
use LeanCommerce\CategoryProductOrderApi\Api\Data\CategoryProductOrderUpdateResponseInterfaceFactory;
use LeanCommerce\CategoryProductOrderApi\Model\Position\NativeCategoryPositionUpdater;
use LeanCommerce\CategoryProductOrderApi\Model\Position\PositionEngineResolver;
use LeanCommerce\CategoryProductOrderApi\Model\Position\VisualMerchandiserPositionUpdater;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Psr\Log\LoggerInterface;

class UpdateCategoryProductOrder implements CategoryProductOrderUpdateInterface
{
    private ProductRepositoryInterface $productRepository;
    private CategoryRepositoryInterface $categoryRepository;
    private PositionEngineResolver $resolver;
    private NativeCategoryPositionUpdater $nativeUpdater;
    private VisualMerchandiserPositionUpdater $vmUpdater;
    private CategoryProductOrderUpdateResponseInterfaceFactory $responseFactory;
    private LoggerInterface $logger;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        PositionEngineResolver $resolver,
        NativeCategoryPositionUpdater $nativeUpdater,
        VisualMerchandiserPositionUpdater $vmUpdater,
        CategoryProductOrderUpdateResponseInterfaceFactory $responseFactory,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->resolver = $resolver;
        $this->nativeUpdater = $nativeUpdater;
        $this->vmUpdater = $vmUpdater;
        $this->responseFactory = $responseFactory;
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

        $frontendPosition = null;

        $response = $this->responseFactory->create();
        $response->setCategoryId($category_id);
        $response->setSku($sku);
        $response->setRequestedPosition($target_position);
        $response->setAppliedPositionSource($engine);
        $response->setAdminPosition($adminPosition);
        $response->setFrontendPosition($frontendPosition);
        $response->setMessage('Position updated. Reindex and frontend validation were skipped for faster response.');

        return $response;
    }
}