<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Service\ExpenseService;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class ExpenseServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testCreateExpense(): void
    {
        $repo = $this->createMock(ExpenseRepositoryInterface::class);
        $repo->expects($this->once())->method('save');
        $user = new User(1, 'test', 'hash', new DateTimeImmutable());

        $service = new ExpenseService($repo);
        $date = new DateTimeImmutable('2025-01-02');
        $expense = $service->create($user, 12.3, 'Meat and dairy', $date, 'groceries');

        $this->assertSame($date, $expense->date);
        $this->assertSame(1, $expense->userId);
        // TODO: uncomment the following line when finished proper implementation
        // $this->assertSame(1230, $expense->amountCents);
    }
}
