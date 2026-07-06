<?php
/**
 * Devsync_FeaturedProduct
 */
declare(strict_types=1);

namespace Devsync\FeaturedProduct\Test\Unit\Model;

use Devsync\FeaturedProduct\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function testIsEnabledReturnsConfiguredFlag(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('featured_product/general/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled());
    }

    public function testGetSkuIsTrimmed(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('featured_product/general/sku', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('  ABC-123  ');

        $this->assertSame('ABC-123', $this->config->getSku());
    }

    public function testGetSkuReturnsEmptyStringWhenNull(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame('', $this->config->getSku());
    }

    public function testGetTitleIsTrimmed(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('  Deal of the day  ');

        $this->assertSame('Deal of the day', $this->config->getTitle());
    }

    public function testGetRefreshIntervalClampsToMinimum(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('1');

        $this->assertSame(2, $this->config->getRefreshInterval());
    }

    public function testGetRefreshIntervalUsesConfiguredValueWhenAboveMinimum(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('30');

        $this->assertSame(30, $this->config->getRefreshInterval());
    }

    public function testGetRefreshIntervalPassesStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('featured_product/general/refresh_interval', ScopeInterface::SCOPE_STORE, 5)
            ->willReturn('12');

        $this->assertSame(12, $this->config->getRefreshInterval(5));
    }
}
