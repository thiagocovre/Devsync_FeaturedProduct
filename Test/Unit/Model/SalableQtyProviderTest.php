<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\Test\Unit\Model;

use Devsync\FeaturedProduct\Model\SalableQtyProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SalableQtyProviderTest extends TestCase
{
    private GetProductSalableQtyInterface&MockObject $getProductSalableQty;
    private StockResolverInterface&MockObject $stockResolver;
    private StoreManagerInterface&MockObject $storeManager;
    private LoggerInterface&MockObject $logger;
    private SalableQtyProvider $provider;

    protected function setUp(): void
    {
        $this->getProductSalableQty = $this->createMock(GetProductSalableQtyInterface::class);
        $this->stockResolver = $this->createMock(StockResolverInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->provider = new SalableQtyProvider(
            $this->getProductSalableQty,
            $this->stockResolver,
            $this->storeManager,
            $this->logger
        );
    }

    public function testReturnsSalableQtyForResolvedWebsiteStock(): void
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');
        $this->storeManager->method('getWebsite')->willReturn($website);

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(1);
        $this->stockResolver->expects($this->once())
            ->method('execute')
            ->with(SalesChannelInterface::TYPE_WEBSITE, 'base')
            ->willReturn($stock);

        $this->getProductSalableQty->expects($this->once())
            ->method('execute')
            ->with('SKU-1', 1)
            ->willReturn(7.0);

        $this->assertSame(7.0, $this->provider->getSalableQty('SKU-1'));
    }

    public function testReturnsZeroForEmptySkuWithoutTouchingInventory(): void
    {
        $this->storeManager->expects($this->never())->method('getWebsite');
        $this->stockResolver->expects($this->never())->method('execute');
        $this->getProductSalableQty->expects($this->never())->method('execute');

        $this->assertSame(0.0, $this->provider->getSalableQty(''));
    }

    public function testReturnsZeroAndLogsWhenInventoryThrows(): void
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');
        $this->storeManager->method('getWebsite')->willReturn($website);

        $this->stockResolver->method('execute')
            ->willThrowException(new LocalizedException(__('Sales channel not found')));

        $this->logger->expects($this->once())->method('warning');

        $this->assertSame(0.0, $this->provider->getSalableQty('SKU-1'));
    }

    public function testReturnsZeroWhenNonLocalizedThrowableIsRaised(): void
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');
        $this->storeManager->method('getWebsite')->willReturn($website);

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(1);
        $this->stockResolver->method('execute')->willReturn($stock);

        // A generic runtime error must still resolve to 0.0 (contract resilience).
        $this->getProductSalableQty->method('execute')
            ->willThrowException(new \RuntimeException('DB gone'));

        $this->logger->expects($this->once())->method('warning');

        $this->assertSame(0.0, $this->provider->getSalableQty('SKU-1'));
    }
}
