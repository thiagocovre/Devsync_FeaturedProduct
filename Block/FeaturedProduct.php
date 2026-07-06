<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\Block;

use Devsync\FeaturedProduct\ViewModel\FeaturedProduct as FeaturedProductViewModel;
use Magento\Framework\View\Element\Template;

/**
 * Thin presentation block for the homepage featured product box.
 *
 * The heavy lifting lives in the injected view model (passed as a block
 * argument in layout XML). This block only owns the jsLayout it hands to the
 * Knockout uiComponent: it takes the static component skeleton declared in
 * layout XML (another block argument) and merges the request-time values
 * (endpoint URL, refresh interval, initial quantity) into it.
 */
class FeaturedProduct extends Template
{
    private const COMPONENT_NAME = 'devsync-featured-stock';

    /**
     * View model injected via `<argument name="view_model" xsi:type="object">`.
     */
    public function getViewModel(): FeaturedProductViewModel
    {
        return $this->getData('view_model');
    }

    /**
     * Merge runtime configuration into the jsLayout skeleton and JSON-encode it
     * for `Magento_Ui/js/core/app` (the standard uiComponent bootstrap).
     *
     * The static skeleton declared as the `jsLayout` block argument is moved by
     * AbstractBlock::__construct() into the protected {@see $jsLayout} property
     * (and unset from the data bag), so we read it from there — NOT from
     * getData('jsLayout'), which would be null and would drop the `component`
     * key, leaving the Knockout component unregistered.
     */
    public function getJsLayout(): string
    {
        $jsLayout = $this->jsLayout ?: [];

        $viewModel = $this->getViewModel();

        $jsLayout['components'][self::COMPONENT_NAME] = array_replace(
            $jsLayout['components'][self::COMPONENT_NAME] ?? [],
            [
                'stockUrl' => $viewModel->getStockUrl(),
                'refreshInterval' => $viewModel->getRefreshInterval(),
                'initialQty' => $viewModel->getSalableQty(),
            ]
        );

        return json_encode($jsLayout, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
}
