<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\Model;

use Devsync\FeaturedProduct\Api\StockProviderInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * MSI (Multi Source Inventory) implementation of the stock provider.
 *
 * Resolves the stock assigned to the current website sales channel and asks
 * the InventorySalesApi service contract for the salable quantity, which is
 * the correct "available for sale" figure on Magento 2.4.x (it already
 * subtracts reservations / out-of-stock thresholds).
 */
class SalableQtyProvider implements StockProviderInterface
{
    public function __construct(
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockResolverInterface $stockResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getSalableQty(string $sku): float
    {
        if ($sku === '') {
            return 0.0;
        }

        try {
            $websiteCode = $this->storeManager->getWebsite()->getCode();
            $stock = $this->stockResolver->execute(
                SalesChannelInterface::TYPE_WEBSITE,
                $websiteCode
            );

            return (float) $this->getProductSalableQty->execute($sku, (int) $stock->getStockId());
        } catch (\Throwable $e) {
            // Any inventory/runtime error (missing SKU, sales channel not found,
            // MSI disabled, DB hiccup, ...) must resolve to 0.0 rather than
            // throwing, so the storefront never breaks over an inventory edge case.
            $this->logger->warning(
                'Devsync_FeaturedProduct: unable to resolve salable qty for SKU "' . $sku . '": '
                . $e->getMessage()
            );

            return 0.0;
        }
    }
}
