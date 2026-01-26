<?php

namespace App\Controller;

use App\Entity\SavingsTransaction;
use App\Repository\SavingsBudgetRepository;
use App\Repository\SavingsTransactionRepository;
use App\Service\BudgetService;
use App\Service\SavingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/savings')]
#[IsGranted('ROLE_USER')]
class SavingsController extends AbstractController
{
    public function __construct(
        private readonly SavingsService $savingsService,
        private readonly BudgetService $budgetService,
        private readonly ValidatorInterface $validator,
        private readonly SavingsTransactionRepository $savingsTransactionRepository
    ) {
    }

    #[Route('', name: 'api_savings_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $user = $this->getUser();
        $savingsBudget = $this->savingsService->getOrCreateSavingsBudget($user);

        return $this->json($this->serializeSavingsBudget($savingsBudget));
    }

    #[Route('/stats', name: 'api_savings_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $user = $this->getUser();
        $savingsBudget = $this->savingsService->getOrCreateSavingsBudget($user);

        return $this->json([
            'balance' => $savingsBudget->getBalance(),
            'totalDeposits' => $savingsBudget->calculateTotalDeposits(),
            'totalWithdrawals' => $savingsBudget->calculateTotalWithdrawals(),
            'weekly' => $this->savingsService->getWeeklySavings($savingsBudget),
            'monthly' => $this->savingsService->getMonthlySavings($savingsBudget),
            'yearly' => $this->savingsService->getYearlySavings($savingsBudget),
        ]);
    }

    #[Route('/transactions', name: 'api_savings_transactions', methods: ['GET'])]
    public function transactions(): JsonResponse
    {
        $user = $this->getUser();
        $savingsBudget = $this->savingsService->getOrCreateSavingsBudget($user);
        $transactions = $savingsBudget->getTransactions()->toArray();

        usort($transactions, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $this->json(array_map(fn($t) => $this->serializeTransaction($t), $transactions));
    }

    #[Route('/energy-drink', name: 'api_savings_energy_drink', methods: ['POST'])]
    public function addEnergyDrinkSavings(): JsonResponse
    {
        $user = $this->getUser();
        $savingsBudget = $this->savingsService->getOrCreateSavingsBudget($user);
        
        $transaction = $this->savingsService->addEnergyDrinkSavings($savingsBudget);

        return $this->json($this->serializeSavingsBudget($savingsBudget), Response::HTTP_OK);
    }

    #[Route('/withdrawal', name: 'api_savings_withdrawal', methods: ['POST'])]
    public function addWithdrawal(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
            return $this->json(['error' => 'Invalid amount'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $savingsBudget = $this->savingsService->getOrCreateSavingsBudget($user);

        try {
            $transaction = $this->savingsService->addWithdrawal(
                $savingsBudget,
                number_format((float)$data['amount'], 2, '.', ''),
                $data['description'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serializeSavingsBudget($savingsBudget), Response::HTTP_OK);
    }

    #[Route('/transfer-to-vacation', name: 'api_savings_transfer_to_vacation', methods: ['POST'])]
    public function transferToVacation(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
            return $this->json(['error' => 'Invalid amount'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $savingsBudget = $this->savingsService->getOrCreateSavingsBudget($user);
        $vacationBudget = $this->budgetService->getOrCreateBudget($user);

        try {
            $this->savingsService->transferToVacationBudget(
                $savingsBudget,
                $vacationBudget,
                number_format((float)$data['amount'], 2, '.', '')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'savingsBudget' => $this->serializeSavingsBudget($savingsBudget),
            'vacationBudget' => $this->serializeVacationBudget($vacationBudget),
        ], Response::HTTP_OK);
    }

    #[Route('/transactions/{id}', name: 'api_savings_transactions_delete', methods: ['DELETE'])]
    public function deleteTransaction(int $id): JsonResponse
    {
        $user = $this->getUser();
        $savingsBudget = $this->savingsService->getOrCreateSavingsBudget($user);
        $transaction = $this->savingsTransactionRepository->find($id);

        if (!$transaction || $transaction->getSavingsBudget() !== $savingsBudget) {
            return $this->json(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
        }

        $this->savingsService->deleteTransaction($transaction);

        return $this->json(['message' => 'Transaction deleted successfully'], Response::HTTP_OK);
    }

    private function serializeSavingsBudget($savingsBudget): array
    {
        return [
            'id' => $savingsBudget->getId(),
            'balance' => $savingsBudget->getBalance(),
            'totalDeposits' => $savingsBudget->calculateTotalDeposits(),
            'totalWithdrawals' => $savingsBudget->calculateTotalWithdrawals(),
        ];
    }

    private function serializeTransaction(SavingsTransaction $transaction): array
    {
        return [
            'id' => $transaction->getId(),
            'type' => $transaction->getType()->value,
            'amount' => $transaction->getAmount(),
            'description' => $transaction->getDescription(),
            'createdAt' => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    private function serializeVacationBudget($budget): array
    {
        return [
            'id' => $budget->getId(),
            'balance' => $budget->getBalance(),
            'totalDeposits' => $budget->calculateTotalDeposits(),
            'totalWithdrawals' => $budget->calculateTotalWithdrawals(),
        ];
    }
}
