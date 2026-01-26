<?php

namespace App\Controller;

use App\DTO\ApplicationDTO;
use App\Entity\Application;
use App\Enum\ApplicationStatus;
use App\Repository\ApplicationRepository;
use App\Service\ApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/applications')]
#[IsGranted('ROLE_USER')]
class ApplicationController extends AbstractController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
        private readonly ApplicationRepository $applicationRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'api_applications_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        $applications = $this->applicationRepository->createQueryBuilder('a')
            ->leftJoin('a.statusHistory', 'sh')
            ->addSelect('sh')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.appliedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn($app) => $this->serializeApplication($app), $applications));
    }

    #[Route('/stats', name: 'api_applications_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $user = $this->getUser();
        $weekly = $this->applicationService->getWeeklyApplications($user);
        $monthly = $this->applicationService->getMonthlyApplications($user);
        $latest = $this->applicationRepository->createQueryBuilder('a')
            ->leftJoin('a.statusHistory', 'sh')
            ->addSelect('sh')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.appliedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->json([
            'weekly' => count($weekly),
            'monthly' => count($monthly),
            'latest' => array_map(fn($app) => $this->serializeApplication($app), $latest),
        ]);
    }

    #[Route('/{id}', name: 'api_applications_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $application = $this->applicationRepository->find($id);
        if (!$application || $application->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Application not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeApplication($application));
    }

    #[Route('', name: 'api_applications_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $appliedAt = null;
        if (isset($data['appliedAt']) && !empty($data['appliedAt'])) {
            try {
                $appliedAt = \DateTimeImmutable::createFromFormat('Y-m-d', $data['appliedAt']);
                if ($appliedAt === false) {
                    $appliedAt = new \DateTimeImmutable($data['appliedAt']);
                }
            } catch (\Exception $e) {
                return $this->json(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
            }
        }
        
        $dto = new ApplicationDTO(
            companyName: $data['companyName'] ?? null,
            position: $data['position'] ?? null,
            platform: $data['platform'] ?? null,
            status: $data['status'] ?? null,
            appliedAt: $appliedAt
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $application = $this->applicationService->create($dto, $this->getUser());

        return $this->json($this->serializeApplication($application), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_applications_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $application = $this->applicationRepository->find($id);
        if (!$application || $application->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Application not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        $appliedAt = null;
        if (isset($data['appliedAt']) && !empty($data['appliedAt'])) {
            try {
                // Try to parse date - supports both YYYY-MM-DD and full datetime formats
                $appliedAt = \DateTimeImmutable::createFromFormat('Y-m-d', $data['appliedAt']);
                if ($appliedAt === false) {
                    $appliedAt = new \DateTimeImmutable($data['appliedAt']);
                }
            } catch (\Exception $e) {
                return $this->json(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            $appliedAt = $application->getAppliedAt();
        }
        
        $dto = new ApplicationDTO(
            companyName: $data['companyName'] ?? $application->getCompanyName(),
            position: $data['position'] ?? $application->getPosition(),
            platform: $data['platform'] ?? $application->getPlatform(),
            status: $data['status'] ?? $application->getStatus()->value,
            appliedAt: $appliedAt
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $application = $this->applicationService->update($application, $dto);

        return $this->json($this->serializeApplication($application));
    }

    #[Route('/{id}/status', name: 'api_applications_change_status', methods: ['PATCH'])]
    public function changeStatus(int $id, Request $request): JsonResponse
    {
        $application = $this->applicationRepository->find($id);
        if (!$application || $application->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Application not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        try {
            ApplicationStatus::from($newStatus);
        } catch (\ValueError $e) {
            return $this->json(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $application = $this->applicationService->changeStatus($application, $newStatus);

        return $this->json($this->serializeApplication($application));
    }

    #[Route('/{id}', name: 'api_applications_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $application = $this->applicationRepository->find($id);
        if (!$application || $application->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Application not found'], Response::HTTP_NOT_FOUND);
        }

        $this->applicationService->delete($application);

        return $this->json(['message' => 'Application deleted successfully'], Response::HTTP_OK);
    }

    private function serializeApplication(Application $application): array
    {
        return [
            'id' => $application->getId(),
            'companyName' => $application->getCompanyName(),
            'position' => $application->getPosition(),
            'platform' => $application->getPlatform(),
            'status' => $application->getStatus()->value,
            'appliedAt' => $application->getAppliedAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $application->getCreatedAt()?->format('Y-m-d H:i:s'),
            'statusHistory' => array_map(fn($history) => [
                'oldStatus' => $history->getOldStatus(),
                'newStatus' => $history->getNewStatus(),
                'changedAt' => $history->getChangedAt()?->format('Y-m-d H:i:s'),
            ], $application->getStatusHistory()->toArray()),
        ];
    }
}
