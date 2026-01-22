<?php

namespace App\Controller;

use App\DTO\GoalDTO;
use App\DTO\TransactionDTO;
use App\Entity\Budget;
use App\Entity\Goal;
use App\Entity\Transaction;
use App\Repository\GoalRepository;
use App\Service\BudgetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/budget')]
#[IsGranted('ROLE_USER')]
class BudgetController extends AbstractController
{
    public function __construct(
        private readonly BudgetService $budgetService,
        private readonly ValidatorInterface $validator,
        private readonly GoalRepository $goalRepository
    ) {
    }

    #[Route('', name: 'api_budget_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $user = $this->getUser();
        $budget = $this->budgetService->getOrCreateBudget($user);

        return $this->json($this->serializeBudget($budget));
    }

    #[Route('/transactions', name: 'api_budget_transactions', methods: ['GET'])]
    public function transactions(): JsonResponse
    {
        $user = $this->getUser();
        $budget = $this->budgetService->getOrCreateBudget($user);
        $transactions = $budget->getTransactions()->toArray();

        usort($transactions, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $this->json(array_map(fn($t) => $this->serializeTransaction($t), $transactions));
    }

    #[Route('/transactions', name: 'api_budget_transactions_create', methods: ['POST'])]
    public function addTransaction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $dto = new TransactionDTO(
            type: $data['type'] ?? null,
            amount: $data['amount'] ?? null,
            description: $data['description'] ?? null
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $budget = $this->budgetService->getOrCreateBudget($user);
        $transaction = $this->budgetService->addTransaction($budget, $dto);

        return $this->json($this->serializeTransaction($transaction), Response::HTTP_CREATED);
    }

    #[Route('/goals', name: 'api_budget_goals', methods: ['GET'])]
    public function goals(): JsonResponse
    {
        $user = $this->getUser();
        $budget = $this->budgetService->getOrCreateBudget($user);
        $goals = $budget->getGoals()->toArray();

        return $this->json(array_map(fn($g) => $this->serializeGoal($g), $goals));
    }

    #[Route('/goals', name: 'api_budget_goals_create', methods: ['POST'])]
    public function addGoal(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $dto = new GoalDTO(
            name: $data['name'] ?? null,
            targetAmount: $data['targetAmount'] ?? null
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $budget = $this->budgetService->getOrCreateBudget($user);
        $goal = $this->budgetService->addGoal($budget, $dto);

        return $this->json($this->serializeGoal($goal), Response::HTTP_CREATED);
    }

    #[Route('/goals/{id}', name: 'api_budget_goals_update', methods: ['PUT'])]
    public function updateGoal(Request $request, int $id): JsonResponse
    {
        $user = $this->getUser();
        $budget = $this->budgetService->getOrCreateBudget($user);
        
        $goal = $this->goalRepository->find($id);
        
        if (!$goal || $goal->getBudget() !== $budget) {
            return $this->json(['error' => 'Goal not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $dto = new GoalDTO(
            name: $data['name'] ?? null,
            targetAmount: $data['targetAmount'] ?? null
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $updatedGoal = $this->budgetService->updateGoal($goal, $dto);

        return $this->json($this->serializeGoal($updatedGoal), Response::HTTP_OK);
    }

    #[Route('/goals/{id}', name: 'api_budget_goals_delete', methods: ['DELETE'])]
    public function deleteGoal(int $id): JsonResponse
    {
        $user = $this->getUser();
        $budget = $this->budgetService->getOrCreateBudget($user);
        
        $goal = $this->goalRepository->find($id);
        
        if (!$goal || $goal->getBudget() !== $budget) {
            return $this->json(['error' => 'Goal not found'], Response::HTTP_NOT_FOUND);
        }

        $this->budgetService->deleteGoal($goal);

        return $this->json(['message' => 'Goal deleted successfully'], Response::HTTP_OK);
    }

    #[Route('/vacation-months', name: 'api_budget_vacation_months', methods: ['PUT'])]
    public function updateVacationMonths(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $vacationMonths = $data['vacationMonths'] ?? null;

        if ($vacationMonths === null || !is_numeric($vacationMonths) || $vacationMonths < 1) {
            return $this->json(['error' => 'Invalid vacation months value'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $budget = $this->budgetService->getOrCreateBudget($user);
        $budget->setVacationMonths((int) $vacationMonths);
        
        $this->budgetService->saveBudget($budget);

        return $this->json($this->serializeBudget($budget), Response::HTTP_OK);
    }

    private function serializeBudget(Budget $budget): array
    {
        return [
            'id' => $budget->getId(),
            'balance' => $budget->getBalance(),
            'totalDeposits' => $budget->calculateTotalDeposits(),
            'totalWithdrawals' => $budget->calculateTotalWithdrawals(),
            'goalsCount' => $budget->getGoals()->count(),
            'vacationMonths' => $budget->getVacationMonths(),
        ];
    }

    private function serializeTransaction(Transaction $transaction): array
    {
        return [
            'id' => $transaction->getId(),
            'type' => $transaction->getType()->value,
            'amount' => $transaction->getAmount(),
            'description' => $transaction->getDescription(),
            'createdAt' => $transaction->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function serializeGoal(Goal $goal): array
    {
        return [
            'id' => $goal->getId(),
            'name' => $goal->getName(),
            'targetAmount' => $goal->getTargetAmount(),
            'isCompleted' => $goal->isCompleted(),
            'completedAt' => $goal->getCompletedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
