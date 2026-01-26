<?php

namespace App\Service;

use App\DTO\GoalDTO;
use App\DTO\TransactionDTO;
use App\Entity\Budget;
use App\Entity\Goal;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\TransactionType;
use Doctrine\ORM\EntityManagerInterface;

class BudgetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function getOrCreateBudget(User $user): Budget
    {
        $budget = $user->getBudget();
        if ($budget === null) {
            $budget = new Budget();
            $budget->setUser($user);
            $this->entityManager->persist($budget);
            $this->entityManager->flush();
        }

        return $budget;
    }

    public function saveBudget(Budget $budget): void
    {
        $this->entityManager->flush();
    }

    public function addTransaction(Budget $budget, TransactionDTO $dto): Transaction
    {
        $transaction = new Transaction();
        $transactionType = TransactionType::from($dto->type);
        $transaction->setType($transactionType);
        $transaction->setAmount($dto->amount);
        $transaction->setDescription($dto->description);
        $transaction->setBudget($budget);

        $currentBalance = (float) $budget->getBalance();
        $amount = (float) $dto->amount;

        if ($transactionType === TransactionType::DEPOSIT) {
            $newBalance = $currentBalance + $amount;
        } else {
            $newBalance = $currentBalance - $amount;
        }

        $budget->setBalance(number_format($newBalance, 2, '.', ''));

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $this->checkGoalsCompletion($budget);

        return $transaction;
    }

    public function addGoal(Budget $budget, GoalDTO $dto): Goal
    {
        $goal = new Goal();
        $goal->setName($dto->name);
        $goal->setTargetAmount($dto->targetAmount);
        $goal->setBudget($budget);

        $this->entityManager->persist($goal);
        $this->entityManager->flush();

        $this->checkGoalsCompletion($budget);

        return $goal;
    }

    public function updateGoal(Goal $goal, GoalDTO $dto): Goal
    {
        $goal->setName($dto->name);
        $goal->setTargetAmount($dto->targetAmount);

        $this->entityManager->flush();

        $this->checkGoalsCompletion($goal->getBudget());

        return $goal;
    }

    public function deleteGoal(Goal $goal): void
    {
        $this->entityManager->remove($goal);
        $this->entityManager->flush();
    }

    public function deleteTransaction(Transaction $transaction): void
    {
        $budget = $transaction->getBudget();
        $amount = $transaction->getAmount();
        $type = $transaction->getType();
        $balance = $budget->getBalance();

        if ($type === TransactionType::DEPOSIT) {
            $newBalance = bcsub($balance, $amount, 2);
        } else {
            $newBalance = bcadd($balance, $amount, 2);
        }
        if (bccomp($newBalance, '0', 2) < 0) {
            $newBalance = '0.00';
        }
        $budget->setBalance($newBalance);
        $this->entityManager->remove($transaction);
        $this->entityManager->flush();
        $this->checkGoalsCompletion($budget);
    }

    private function checkGoalsCompletion(Budget $budget): void
    {
        $balance = $budget->getBalance();
        $goals = $budget->getGoals()->toArray();
        usort($goals, fn($a, $b) => $a->getId() <=> $b->getId());

        $remaining = $balance;
        foreach ($goals as $goal) {
            $target = $goal->getTargetAmount();
            $allocated = bccomp($remaining, $target, 2) >= 0 ? $target : $remaining;
            $goal->setIsCompleted(bccomp($allocated, $target, 2) >= 0);
            $remaining = bcsub($remaining, $allocated, 2);
            if (bccomp($remaining, '0', 2) < 0) {
                $remaining = '0.00';
            }
        }
        $this->entityManager->flush();
    }
}
