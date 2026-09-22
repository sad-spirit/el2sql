<?php

/*
 * This file is part of sad_spirit/el2sql:
 * Translator of Symfony's ExpressionLanguage to Postgres SQL dialect
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\el2sql\tests;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Creates helpful mocks for PSR-6 cache
 *
 * @psalm-require-extends TestCase
 */
trait Psr6Mocks
{
    protected function getMockForNoCache(): CacheItemPoolInterface
    {
        $mockPool = $this->createMock(CacheItemPoolInterface::class);

        $mockPool->expects($this->never())
            ->method('getItem');

        return $mockPool;
    }

    protected function getMockForCacheMiss(mixed $value): CacheItemPoolInterface
    {
        $mockPool = $this->createMock(CacheItemPoolInterface::class);
        $mockItem = $this->createMock(CacheItemInterface::class);

        $mockPool->expects($this->atLeastOnce())
            ->method('getItem')
            ->willReturn($mockItem);

        $mockPool->expects($this->once())
            ->method('save')
            ->with($mockItem);

        $mockItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $mockItem->expects($this->once())
            ->method('set')
            ->with($value)
            ->willReturnSelf();

        return $mockPool;
    }

    protected function getMockForCacheHit(mixed $value): CacheItemPoolInterface
    {
        $mockPool = $this->createMock(CacheItemPoolInterface::class);
        $mockItem = $this->createMock(CacheItemInterface::class);

        $mockPool->expects($this->once())
            ->method('getItem')
            ->willReturn($mockItem);

        $mockPool->expects($this->never())
            ->method('save');
        $mockItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);

        $mockItem->expects($this->once())
            ->method('get')
            ->willReturn($value);

        $mockItem->expects($this->never())
            ->method('set');

        return $mockPool;
    }
}
