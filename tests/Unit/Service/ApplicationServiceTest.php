<?php

namespace App\Tests\Unit\Service;

use App\DTO\ApplicationDTO;
use App\Entity\Application;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Repository\ApplicationRepository;
use App\Service\ApplicationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ApplicationServiceTest extends TestCase
{
    private ApplicationService $applicationService;
    private EntityManagerInterface $entityManager;
    private ApplicationRepository $applicationRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->applicationRepository = $this->createMock(ApplicationRepository::class);
        
        $this->applicationService = new ApplicationService(
            $this->entityManager,
            $this->applicationRepository
        );
    }

    public function testCreateApplication(): void
    {
        $user = $this->createMock(User::class);
        $dto = new ApplicationDTO(
            companyName: 'Test Company',
            position: 'Developer',
            platform: 'LinkedIn',
            status: ApplicationStatus::APPLIED->value
        );

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($application) {
                return $application instanceof Application
                    && $application->getCompanyName() === 'Test Company'
                    && $application->getPosition() === 'Developer'
                    && $application->getPlatform() === 'LinkedIn'
                    && $application->getStatus() === ApplicationStatus::APPLIED;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $application = $this->applicationService->create($dto, $user);

        $this->assertInstanceOf(Application::class, $application);
        $this->assertEquals('Test Company', $application->getCompanyName());
    }

    public function testUpdateApplication(): void
    {
        $application = $this->createMock(Application::class);
        $dto = new ApplicationDTO(
            companyName: 'Updated Company',
            position: 'Senior Developer',
            platform: 'Indeed',
            status: ApplicationStatus::INTERVIEW->value
        );

        $application->expects($this->once())
            ->method('setCompanyName')
            ->with('Updated Company');

        $application->expects($this->once())
            ->method('setPosition')
            ->with('Senior Developer');

        $application->expects($this->once())
            ->method('setPlatform')
            ->with('Indeed');

        $application->expects($this->once())
            ->method('setStatus')
            ->with(ApplicationStatus::INTERVIEW);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->applicationService->update($application, $dto);

        $this->assertSame($application, $result);
    }

    public function testChangeStatus(): void
    {
        $application = $this->createMock(Application::class);

        $application->expects($this->once())
            ->method('setStatus')
            ->with(ApplicationStatus::GOT_JOB);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->applicationService->changeStatus($application, ApplicationStatus::GOT_JOB->value);

        $this->assertSame($application, $result);
    }

    public function testDeleteApplication(): void
    {
        $application = $this->createMock(Application::class);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($application);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->applicationService->delete($application);
    }
}
