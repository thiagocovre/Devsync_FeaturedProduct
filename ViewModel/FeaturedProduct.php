<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\ViewModel;

use Devsync\FeaturedProduct\Api\StockProviderInterface;
use Devsync\FeaturedProduct\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * View model that feeds the featured product template.
 *
 * Passed to the block as a `view_model` block argument (keeps the block thin
 * and the presentation logic unit-testable / reusable). It resolves the
 * configured product once per request and exposes only presentation-ready
 * values to the template.
 */
class FeaturedProduct implements ArgumentInterface
{
    private const IMAGE_ID = 'product_base_image';

    private ?ProductInterface $product = null;
    private bool $productResolved = false;

    public function __construct(
        private readonly Config $config,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StockProviderInterface $stockProvider,
        private readonly ImageHelper $imageHelper,
        private readonly PricingHelper $pricingHelper,
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * The box is only rendered when enabled AND a valid product is configured.
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->getProduct() !== null;
    }

    public function getBoxTitle(): string
    {
        return $this->config->getTitle();
    }

    public function getName(): string
    {
        $product = $this->getProduct();

        return $product ? (string) $product->getName() : '';
    }

    /**
     * Final price formatted with the store currency (plain string, no HTML).
     */
    public function getFormattedPrice(): string
    {
        $product = $this->getProduct();

        if ($product === null) {
            return '';
        }

        return (string) $this->pricingHelper->currency(
            (float) $product->getFinalPrice(),
            true,
            false
        );
    }

    public function getImageUrl(): string
    {
        $product = $this->getProduct();

        if ($product === null) {
            return '';
        }

        return (string) $this->imageHelper
            ->init($product, self::IMAGE_ID)
            ->getUrl();
    }

    public function getProductUrl(): string
    {
        $product = $this->getProduct();

        return $product ? (string) $product->getProductUrl() : '#';
    }

    /**
     * Initial salable quantity, rendered server-side for the first paint.
     * Subsequent values are refreshed by the Knockout component.
     */
    public function getSalableQty(): float
    {
        $product = $this->getProduct();

        if ($product === null) {
            return 0.0;
        }

        return $this->stockProvider->getSalableQty((string) $product->getSku());
    }

    public function getRefreshInterval(): int
    {
        return $this->config->getRefreshInterval();
    }

    /**
     * Endpoint polled by the KO component. It intentionally carries NO product
     * identifier: the controller re-reads the configured SKU server-side, so a
     * visitor can never probe arbitrary SKUs through this URL.
     */
    public function getStockUrl(): string
    {
        return $this->urlBuilder->getUrl('featuredproduct/stock/index');
    }

    /**
     * Resolve (once) the configured product for the current store scope.
     */
    private function getProduct(): ?ProductInterface
    {
        if ($this->productResolved) {
            return $this->product;
        }

        $this->productResolved = true;
        $sku = $this->config->getSku();

        if ($sku === '') {
            return $this->product = null;
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $product = $this->productRepository->get($sku, false, $storeId);

            // Only surface a product that is actually purchasable on the storefront.
            $this->product = $product->isSaleable() ? $product : null;
        } catch (NoSuchEntityException $e) {
            $this->product = null;
        }

        return $this->product;
    }
}
