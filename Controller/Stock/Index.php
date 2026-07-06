<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\Controller\Stock;

use Devsync\FeaturedProduct\Api\StockProviderInterface;
use Devsync\FeaturedProduct\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Real-time stock endpoint (GET featuredproduct/stock/index).
 *
 * Returns the salable quantity of the *configured* product as JSON. The SKU is
 * read from store configuration and never from the request, so the endpoint
 * cannot be used to enumerate stock of arbitrary products.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly Config $config,
        private readonly StockProviderInterface $stockProvider
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        // The real-time value must never be cached by the browser, a CDN or
        // Varnish/FPC — otherwise the quantity would go stale between polls.
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);

        $sku = $this->config->getSku();

        if (!$this->config->isEnabled() || $sku === '') {
            return $result->setData([
                'success' => false,
                'qty' => 0,
            ]);
        }

        return $result->setData([
            'success' => true,
            'qty' => $this->stockProvider->getSalableQty($sku),
        ]);
    }
}
