<?php

namespace App\Service;

use App\DTO\ApplicationDTO;
use App\Entity\Application;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Repository\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;

class ApplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApplicationRepository $applicationRepository
    ) {
    }

    public function create(ApplicationDTO $dto, User $user): Application
    {
        $application = new Application();
        $application->setCompanyName($dto->companyName);
        $application->setPosition($dto->position);
        $application->setPlatform($dto->platform);
        $status = $dto->status 
            ? ApplicationStatus::from($dto->status) 
            : ApplicationStatus::APPLIED;
        $application->setStatus($status);
        $application->setAppliedAt($dto->appliedAt ?? new \DateTimeImmutable());
        $application->setUser($user);

        $this->entityManager->persist($application);
        $this->entityManager->flush();

        return $application;
    }

    public function update(Application $application, ApplicationDTO $dto): Application
    {
        $application->setCompanyName($dto->companyName);
        $application->setPosition($dto->position);
        $application->setPlatform($dto->platform);
        $application->setStatus(ApplicationStatus::from($dto->status));
        if ($dto->appliedAt) {
            $application->setAppliedAt($dto->appliedAt);
        }

        $this->entityManager->flush();

        return $application;
    }

    public function changeStatus(Application $application, string $newStatus): Application
    {
        $status = ApplicationStatus::from($newStatus);
        $application->setStatus($status);
        $this->entityManager->flush();

        return $application;
    }

    public function delete(Application $application): void
    {
        $this->entityManager->remove($application);
        $this->entityManager->flush();
    }

    /**
     * @return Application[]
     */
    public function getWeeklyApplications(User $user): array
    {
        $startDate = new \DateTimeImmutable('monday this week');
        $endDate = new \DateTimeImmutable('sunday this week 23:59:59');

        return $this->applicationRepository->findByUserAndDateRange(
            $user->getId(),
            $startDate,
            $endDate
        );
    }

    /**
     * @return Application[]
     */
    public function getMonthlyApplications(User $user): array
    {
        $startDate = new \DateTimeImmutable('first day of this month');
        $endDate = new \DateTimeImmutable('last day of this month 23:59:59');

        return $this->applicationRepository->findByUserAndDateRange(
            $user->getId(),
            $startDate,
            $endDate
        );
    }
}
