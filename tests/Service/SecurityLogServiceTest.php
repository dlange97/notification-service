<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\SecurityLogRepository;
use App\Service\SecurityLogService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SecurityLogServiceTest extends TestCase
{
    private SecurityLogRepository&MockObject $repository;
    private SecurityLogService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SecurityLogRepository::class);
        $this->service    = new SecurityLogService($this->repository);
    }

    public function testGetPaginatedListReturnsMappedItems(): void
    {
        $this->repository->method('countAll')->willReturn(1);
        $this->repository->method('findPaginated')->with(50, 0)->willReturn([
            ['id' => '3', 'ip' => '192.168.1.1', 'path' => '/notification/send', 'method' => 'POST', 'instance_id' => 'inst-2', 'is_sensitive' => '1', 'user_agent' => 'agent', 'created_at' => '2026-01-02 00:00:00'],
        ]);

        $result = $this->service->getPaginatedList(1, 50, 'notification');

        $this->assertSame('notification', $result['service']);
        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertTrue($result['items'][0]['isSensitive']);
    }

    public function testGetPaginatedListClampsPageAndPerPage(): void
    {
        $this->repository->method('countAll')->willReturn(0);
        $this->repository->expects($this->once())->method('findPaginated')->with(100, 0)->willReturn([]);

        $this->service->getPaginatedList(-5, 500, 'notification');
    }

    public function testClearDelegatesAndReturnsCount(): void
    {
        $this->repository->expects($this->once())->method('clearAll')->willReturn(3);

        $this->assertSame(3, $this->service->clear());
    }
}
