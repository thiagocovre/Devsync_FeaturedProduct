<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\Test\Unit\Block;

use Devsync\FeaturedProduct\Block\FeaturedProduct;
use Devsync\FeaturedProduct\ViewModel\FeaturedProduct as FeaturedProductViewModel;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class FeaturedProductTest extends TestCase
{
    private ObjectManager $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
    }

    public function testGetViewModelReturnsInjectedArgument(): void
    {
        $viewModel = $this->createMock(FeaturedProductViewModel::class);

        /** @var FeaturedProduct $block */
        $block = $this->objectManager->getObject(FeaturedProduct::class, [
            'data' => ['view_model' => $viewModel],
        ]);

        $this->assertSame($viewModel, $block->getViewModel());
    }

    public function testGetJsLayoutMergesRuntimeValuesIntoSkeleton(): void
    {
        $viewModel = $this->createMock(FeaturedProductViewModel::class);
        $viewModel->method('getStockUrl')->willReturn('http://localhost/featuredproduct/stock/index');
        $viewModel->method('getRefreshInterval')->willReturn(15);
        $viewModel->method('getSalableQty')->willReturn(5.0);

        // The `jsLayout` data key is consumed by AbstractBlock::__construct and
        // moved to the protected $jsLayout property (the exact behaviour the
        // block relies on to keep the `component` key).
        $skeleton = [
            'components' => [
                'devsync-featured-stock' => [
                    'component' => 'Devsync_FeaturedProduct/js/view/stock',
                ],
            ],
        ];

        /** @var FeaturedProduct $block */
        $block = $this->objectManager->getObject(FeaturedProduct::class, [
            'data' => [
                'view_model' => $viewModel,
                'jsLayout' => $skeleton,
            ],
        ]);

        $decoded = json_decode($block->getJsLayout(), true);
        $component = $decoded['components']['devsync-featured-stock'];

        // The JS component name from the skeleton must survive (regression guard
        // for the getData('jsLayout') vs $this->jsLayout bug).
        $this->assertSame('Devsync_FeaturedProduct/js/view/stock', $component['component']);
        $this->assertSame('http://localhost/featuredproduct/stock/index', $component['stockUrl']);
        $this->assertSame(15, $component['refreshInterval']);
        // JSON encoding collapses 5.0 to 5, so compare loosely by value.
        $this->assertEquals(5.0, $component['initialQty']);
    }
}
