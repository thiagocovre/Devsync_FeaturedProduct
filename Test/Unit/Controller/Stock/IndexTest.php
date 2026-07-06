<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\Test\Unit\Controller\Stock;

use Devsync\FeaturedProduct\Api\StockProviderInterface;
use Devsync\FeaturedProduct\Controller\Stock\Index;
use Devsync\FeaturedProduct\Model\Config;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private JsonFactory&MockObject $jsonFactory;
    private Json&MockObject $jsonResult;
    private Config&MockObject $config;
    private StockProviderInterface&MockObject $stockProvider;
    private Index $controller;

    protected function setUp(): void
    {
        $this->jsonResult = $this->createMock(Json::class);
        $this->jsonResult->method('setHeader')->willReturnSelf();
        $this->jsonResult->method('setData')->willReturnSelf();

        $this->jsonFactory = $this->createMock(JsonFactory::class);
        $this->jsonFactory->method('create')->willReturn($this->jsonResult);

        $this->config = $this->createMock(Config::class);
        $this->stockProvider = $this->createMock(StockProviderInterface::class);

        $this->controller = new Index($this->jsonFactory, $this->config, $this->stockProvider);
    }

    public function testAlwaysSetsNoStoreCacheHeader(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('SKU-1');
        $this->stockProvider->method('getSalableQty')->willReturn(3.0);

        $this->jsonResult->expects($this->once())
            ->method('setHeader')
            ->with('Cache-Control', 'no-store, no-cache, must-revalidate', true)
            ->willReturnSelf();

        $this->controller->execute();
    }

    public function testReturnsSalableQtyWhenEnabled(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('SKU-1');
        $this->stockProvider->expects($this->once())
            ->method('getSalableQty')
            ->with('SKU-1')
            ->willReturn(42.0);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with(['success' => true, 'qty' => 42.0])
            ->willReturnSelf();

        $this->assertSame($this->jsonResult, $this->controller->execute());
    }

    public function testReturnsFailureWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->config->method('getSku')->willReturn('SKU-1');
        $this->stockProvider->expects($this->never())->method('getSalableQty');

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with(['success' => false, 'qty' => 0])
            ->willReturnSelf();

        $this->controller->execute();
    }

    public function testReturnsFailureWhenSkuIsEmpty(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getSku')->willReturn('');
        $this->stockProvider->expects($this->never())->method('getSalableQty');

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with(['success' => false, 'qty' => 0])
            ->willReturnSelf();

        $this->controller->execute();
    }
}
