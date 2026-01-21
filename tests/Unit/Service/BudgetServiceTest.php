<?php

namespace App\Tests\Unit\Service;

use App\DTO\GoalDTO;
use App\DTO\TransactionDTO;
use App\Entity\Budget;
use App\Entity\Goal;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\TransactionType;
use App\Service\BudgetService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BudgetServiceTest extends TestCase
{
    private BudgetService $budgetService;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->budgetService = new BudgetService($this->entityManager);
    }

    public function testGetOrCreateBudgetReturnsExistingBudget(): void
    {
        $user = $this->createMock(User::class);
        $budget = $this->createMock(Budget::class);

        $user->expects($this->once())
            ->method('getBudget')
            ->willReturn($budget);

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $result = $this->budgetService->getOrCreateBudget($user);

        $this->assertSame($budget, $result);
    }

    public function testGetOrCreateBudgetCreatesNewBudget(): void
    {
        $user = $this->createMock(User::class);

        $user->expects($this->once())
            ->method('getBudget')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($budget) use ($user) {
                return $budget instanceof Budget
                    && $budget->getUser() === $user;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->budgetService->getOrCreateBudget($user);

        $this->assertInstanceOf(Budget::class, $result);
    }

    public function testAddTransactionCreatesDepositTransaction(): void
    {
        $budget = $this->createMock(Budget::class);
        $dto = new TransactionDTO(
            type: TransactionType::DEPOSIT->value,
            amount: '100.00',
            description: 'Test deposit'
        );

        $budget->expects($this->once())
            ->method('getBalance')
            ->willReturn('50.00');

        $budget->expects($this->once())
            ->method('setBalance')
            ->with('150.00');

        $budget->expects($this->once())
            ->method('getGoals')
            ->willReturn(new ArrayCollection());

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Transaction::class));

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $transaction = $this->budgetService->addTransaction($budget, $dto);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionType::DEPOSIT, $transaction->getType());
        $this->assertEquals('100.00', $transaction->getAmount());
    }

    public function testAddTransactionCreatesWithdrawalTransaction(): void
    {
        $budget = $this->createMock(Budget::class);
        $dto = new TransactionDTO(
            type: TransactionType::WITHDRAWAL->value,
            amount: '25.00',
            description: 'Test withdrawal'
        );

        $budget->expects($this->once())
            ->method('getBalance')
            ->willReturn('100.00');

        $budget->expects($this->once())
            ->method('setBalance')
            ->with('75.00');

        $budget->expects($this->once())
            ->method('getGoals')
            ->willReturn(new ArrayCollection());

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Transaction::class));

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $transaction = $this->budgetService->addTransaction($budget, $dto);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionType::WITHDRAWAL, $transaction->getType());
    }

    public function testAddGoalCreatesGoal(): void
    {
        $budget = $this->createMock(Budget::class);
        $dto = new GoalDTO(
            name: 'New Car',
            targetAmount: '5000.00'
        );

        $budget->expects($this->once())
            ->method('getGoals')
            ->willReturn(new ArrayCollection());

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($goal) {
                return $goal instanceof Goal
                    && $goal->getName() === 'New Car'
                    && $goal->getTargetAmount() === '5000.00';
            }));

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $goal = $this->budgetService->addGoal($budget, $dto);

        $this->assertInstanceOf(Goal::class, $goal);
        $this->assertEquals('New Car', $goal->getName());
        $this->assertEquals('5000.00', $goal->getTargetAmount());
    }
}
