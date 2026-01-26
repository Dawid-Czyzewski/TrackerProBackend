<?php

namespace App\Service;

use App\Entity\Budget;
use App\Entity\SavingsBudget;
use App\Entity\SavingsTransaction;
use App\Entity\User;
use App\Enum\TransactionType;
use App\Repository\SavingsBudgetRepository;
use App\Repository\SavingsTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class SavingsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SavingsBudgetRepository $savingsBudgetRepository,
        private readonly SavingsTransactionRepository $savingsTransactionRepository
    ) {
    }

    public function getOrCreateSavingsBudget(User $user): SavingsBudget
    {
        $savingsBudget = $this->savingsBudgetRepository->findOneBy(['user' => $user]);
        
        if (!$savingsBudget) {
            $savingsBudget = new SavingsBudget();
            $savingsBudget->setUser($user);
            $savingsBudget->setBalance('0.00');
            $this->entityManager->persist($savingsBudget);
            $this->entityManager->flush();
        }

        return $savingsBudget;
    }

    public function addEnergyDrinkSavings(SavingsBudget $savingsBudget): SavingsTransaction
    {
        $amount = '7.00';
        $transaction = new SavingsTransaction();
        $transaction->setSavingsBudget($savingsBudget);
        $transaction->setType(TransactionType::DEPOSIT);
        $transaction->setAmount($amount);
        $transaction->setDescription('savings.energy_drink');

        $newBalance = bcadd($savingsBudget->getBalance(), $amount, 2);
        $savingsBudget->setBalance($newBalance);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    public function addWithdrawal(SavingsBudget $savingsBudget, string $amount, ?string $description = null): SavingsTransaction
    {
        $transaction = new SavingsTransaction();
        $transaction->setSavingsBudget($savingsBudget);
        $transaction->setType(TransactionType::WITHDRAWAL);
        $transaction->setAmount($amount);
        $transaction->setDescription($description);

        $newBalance = bcsub($savingsBudget->getBalance(), $amount, 2);
        if (bccomp($newBalance, '0', 2) < 0) {
            throw new \InvalidArgumentException('Insufficient balance');
        }

        $savingsBudget->setBalance($newBalance);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    public function transferToVacationBudget(SavingsBudget $savingsBudget, Budget $vacationBudget, string $amount): SavingsTransaction
    {
        if (bccomp($savingsBudget->getBalance(), $amount, 2) < 0) {
            throw new \InvalidArgumentException('Insufficient balance');
        }

        $withdrawal = new SavingsTransaction();
        $withdrawal->setSavingsBudget($savingsBudget);
        $withdrawal->setType(TransactionType::WITHDRAWAL);
        $withdrawal->setAmount($amount);
        $withdrawal->setDescription('savings.transfer_to_vacation');

        $newSavingsBalance = bcsub($savingsBudget->getBalance(), $amount, 2);
        $savingsBudget->setBalance($newSavingsBalance);

        $deposit = new \App\Entity\Transaction();
        $deposit->setBudget($vacationBudget);
        $deposit->setType(TransactionType::DEPOSIT);
        $deposit->setAmount($amount);
        $deposit->setDescription('savings.transfer_from_savings');

        $newVacationBalance = bcadd($vacationBudget->getBalance(), $amount, 2);
        $vacationBudget->setBalance($newVacationBalance);

        $this->entityManager->persist($withdrawal);
        $this->entityManager->persist($deposit);
        $this->entityManager->flush();

        return $withdrawal;
    }

    public function getWeeklySavings(SavingsBudget $savingsBudget): string
    {
        $now = new \DateTimeImmutable();
        $weekStart = $now->modify('monday this week')->setTime(0, 0, 0);
        
        $total = '0.00';
        foreach ($savingsBudget->getTransactions() as $transaction) {
            if ($transaction->getType() === TransactionType::DEPOSIT && $transaction->getCreatedAt() >= $weekStart) {
                $total = bcadd($total, $transaction->getAmount(), 2);
            }
        }

        return $total;
    }

    public function getMonthlySavings(SavingsBudget $savingsBudget): string
    {
        $now = new \DateTimeImmutable();
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        
        $total = '0.00';
        foreach ($savingsBudget->getTransactions() as $transaction) {
            if ($transaction->getType() === TransactionType::DEPOSIT && $transaction->getCreatedAt() >= $monthStart) {
                $total = bcadd($total, $transaction->getAmount(), 2);
            }
        }

        return $total;
    }

    public function getYearlySavings(SavingsBudget $savingsBudget): string
    {
        $now = new \DateTimeImmutable();
        $yearStart = $now->modify('first day of january this year')->setTime(0, 0, 0);
        
        $total = '0.00';
        foreach ($savingsBudget->getTransactions() as $transaction) {
            if ($transaction->getType() === TransactionType::DEPOSIT && $transaction->getCreatedAt() >= $yearStart) {
                $total = bcadd($total, $transaction->getAmount(), 2);
            }
        }

        return $total;
    }

    public function deleteTransaction(SavingsTransaction $transaction): void
    {
        $savingsBudget = $transaction->getSavingsBudget();
        $amount = $transaction->getAmount();
        $type = $transaction->getType();

        if ($type === TransactionType::DEPOSIT) {
            $newBalance = bcsub($savingsBudget->getBalance(), $amount, 2);
            if (bccomp($newBalance, '0', 2) < 0) {
                $newBalance = '0.00';
            }
        } else {
            $newBalance = bcadd($savingsBudget->getBalance(), $amount, 2);
        }

        $savingsBudget->setBalance($newBalance);
        $this->entityManager->remove($transaction);
        $this->entityManager->flush();
    }
}
