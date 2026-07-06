<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed accessor for the module store configuration.
 *
 * Centralises every system.xml path so the rest of the module never deals with
 * raw configuration strings (single source of truth / no magic strings).
 */
class Config
{
    private const XML_PATH_ENABLED = 'featured_product/general/enabled';
    private const XML_PATH_SKU = 'featured_product/general/sku';
    private const XML_PATH_TITLE = 'featured_product/general/title';
    private const XML_PATH_REFRESH_INTERVAL = 'featured_product/general/refresh_interval';

    /**
     * Hard lower bound to protect the store from an over-aggressive polling value.
     */
    private const MIN_REFRESH_INTERVAL = 2;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSku(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_SKU,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getTitle(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getRefreshInterval(?int $storeId = null): int
    {
        $interval = (int) $this->scopeConfig->getValue(
            self::XML_PATH_REFRESH_INTERVAL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return max(self::MIN_REFRESH_INTERVAL, $interval);
    }
}
