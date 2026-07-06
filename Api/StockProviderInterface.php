<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\Api;

/**
 * Service contract that returns the quantity currently available for sale
 * (salable quantity) of a given product SKU on the current sales channel.
 *
 * Exposed as an interface so the real-time source can be swapped (MSI, a flat
 * legacy stock item, an ERP webservice, ...) through a di.xml preference,
 * without touching the block, view model or controller that consume it.
 */
interface StockProviderInterface
{
    /**
     * Return the salable quantity for the given SKU on the current website stock.
     *
     * Implementations must be resilient: a missing product, a disabled MSI
     * module or any inventory error must resolve to 0.0 rather than throwing,
     * so the storefront never breaks because of an inventory edge case.
     *
     * @param string $sku
     * @return float
     */
    public function getSalableQty(string $sku): float;
}
