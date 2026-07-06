<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\Test\Unit\ViewModel;

use Devsync\FeaturedProduct\Api\StockProviderInterface;
use Devsync\FeaturedProduct\Model\Config;
use Devsync\FeaturedProduct\ViewModel\FeaturedProduct;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FeaturedProductTest extends TestCase
{
    private Config&MockObject $config;
    private ProductRepositoryInterface&MockObject $productRepository;
    private StockProviderInterface&MockObject $stockProvider;
    private ImageHelper&MockObject $imageHelper;
    private PricingHelper&MockObject $pricingHelper;
    private UrlInterface&MockObject $urlBuilder;
    private StoreManagerInterface&MockObject $storeManager;
    private FeaturedProduct $viewModel;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->stockProvider = $this->createMock(StockProviderInterface::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->pricingHelper = $this->createMock(PricingHelper::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $this->viewModel = new FeaturedProduct(
            $this->config,
            $this->productRepository,
            $this->stockProvider,
            $this->imageHelper,
            $this->pricingHelper,
            $this->urlBuilder,
            $this->storeManager
        );
    }

    private function configureStore(int $storeId = 1): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $this->storeManager->method('getStore')->willReturn($store);
    }

    private function buildProduct(): Product&MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('isSaleable')->willReturn(true);
        $product->method('getName')->willReturn('Cool Product');
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getFinalPrice')->willReturn(9.99);
        $product->method('getProductUrl')->willReturn('http://localhost/cool-product.html');

        return $product;
    }

    public function testIsDisabledWhenConfigDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->productRepository->expects($this->never())->method('get');

        $this->assertFalse($this->viewModel->isEnabled());
    }

    public function testIsDisabledWhenSkuIsEmpty(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('');
        $this->productRepository->expects($this->never())->method('get');

        $this->assertFalse($this->viewModel->isEnabled());
    }

    public function testIsDisabledWhenProductDoesNotExist(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('MISSING');
        $this->configureStore();
        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('No such product')));

        $this->assertFalse($this->viewModel->isEnabled());
    }

    public function testIsDisabledWhenProductNotSaleable(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('isSaleable')->willReturn(false);

        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('SKU-1');
        $this->configureStore();
        $this->productRepository->method('get')->willReturn($product);

        $this->assertFalse($this->viewModel->isEnabled());
    }

    public function testIsEnabledWithSaleableProduct(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('SKU-1');
        $this->configureStore();
        $this->productRepository->method('get')->willReturn($this->buildProduct());

        $this->assertTrue($this->viewModel->isEnabled());
    }

    public function testExposesProductPresentationData(): void
    {
        $product = $this->buildProduct();
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('SKU-1');
        $this->config->method('getTitle')->willReturn('Deal of the day');
        $this->config->method('getRefreshInterval')->willReturn(15);
        $this->configureStore();
        $this->productRepository->method('get')->with('SKU-1', false, 1)->willReturn($product);

        $this->imageHelper->method('init')
            ->with($product, 'product_base_image')
            ->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('http://localhost/media/base.jpg');

        $this->pricingHelper->method('currency')->with(9.99, true, false)->willReturn('$9.99');
        $this->stockProvider->method('getSalableQty')->with('SKU-1')->willReturn(5.0);
        $this->urlBuilder->method('getUrl')
            ->with('featuredproduct/stock/index')
            ->willReturn('http://localhost/featuredproduct/stock/index');

        $this->assertSame('Cool Product', $this->viewModel->getName());
        $this->assertSame('Deal of the day', $this->viewModel->getBoxTitle());
        $this->assertSame('$9.99', $this->viewModel->getFormattedPrice());
        $this->assertSame('http://localhost/media/base.jpg', $this->viewModel->getImageUrl());
        $this->assertSame('http://localhost/cool-product.html', $this->viewModel->getProductUrl());
        $this->assertSame(5.0, $this->viewModel->getSalableQty());
        $this->assertSame(15, $this->viewModel->getRefreshInterval());
        $this->assertSame('http://localhost/featuredproduct/stock/index', $this->viewModel->getStockUrl());
    }

    public function testProductIsResolvedOnlyOncePerRequest(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('SKU-1');
        $this->configureStore();

        // Even though several getters are called, the repository is hit once.
        $this->productRepository->expects($this->once())
            ->method('get')
            ->willReturn($this->buildProduct());

        $this->viewModel->isEnabled();
        $this->viewModel->getName();
        $this->viewModel->getProductUrl();
    }

    public function testGettersReturnEmptyWhenNoProduct(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('');

        $this->assertSame('', $this->viewModel->getName());
        $this->assertSame('', $this->viewModel->getFormattedPrice());
        $this->assertSame('', $this->viewModel->getImageUrl());
        $this->assertSame('#', $this->viewModel->getProductUrl());
        $this->assertSame(0.0, $this->viewModel->getSalableQty());
    }
}
