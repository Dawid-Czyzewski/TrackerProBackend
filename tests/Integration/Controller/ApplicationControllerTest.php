<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Application;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApplicationControllerTest extends WebTestCase
{
    private $client;
    private ?EntityManagerInterface $entityManager = null;
    private ?User $testUser = null;
    private ?string $token = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        $this->testUser = new User();
        $this->testUser->setEmail('apptest' . uniqid() . '@example.com');
        $this->testUser->setPassword('$2y$13$test');
        $this->testUser->setFirstName('Test');
        $this->testUser->setLastName('User');
        $this->testUser->setIsVerified(true);
        
        $this->entityManager->persist($this->testUser);
        $this->entityManager->flush();

        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $this->token = $jwtManager->create($this->testUser);
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager) && $this->entityManager->isOpen()) {
            try {
                $user = $this->entityManager->getRepository(User::class)->find($this->testUser->getId());
                if ($user) {
                    foreach ($user->getApplications() as $application) {
                        $this->entityManager->remove($application);
                    }
                    $budget = $user->getBudget();
                    if ($budget) {
                        foreach ($budget->getTransactions() as $transaction) {
                            $this->entityManager->remove($transaction);
                        }
                        foreach ($budget->getGoals() as $goal) {
                            $this->entityManager->remove($goal);
                        }
                        $this->entityManager->remove($budget);
                    }
                    $this->entityManager->remove($user);
                    $this->entityManager->flush();
                }
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->entityManager = null;
        $this->client = null;
        $this->testUser = null;
        $this->token = null;
        parent::tearDown();
    }

    public function testListApplicationsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/applications');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateApplication(): void
    {
        $this->client->request(
            'POST',
            '/api/applications',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token
            ],
            json_encode([
                'companyName' => 'Test Company',
                'position' => 'Developer',
                'platform' => 'LinkedIn',
                'status' => ApplicationStatus::APPLIED->value
            ])
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Test Company', $response['companyName']);
        $this->assertEquals('Developer', $response['position']);
    }

    public function testCreateApplicationWithInvalidData(): void
    {
        $this->client->request(
            'POST',
            '/api/applications',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token
            ],
            json_encode([
                'companyName' => '',
                'position' => 'Developer',
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testListApplications(): void
    {
        $application = new Application();
        $application->setCompanyName('Test Company');
        $application->setPosition('Developer');
        $application->setStatus(ApplicationStatus::APPLIED);
        $application->setUser($this->testUser);
        $this->entityManager->persist($application);
        $this->entityManager->flush();

        $this->client->request(
            'GET',
            '/api/applications',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(1, count($response));
    }

    public function testUpdateApplication(): void
    {
        $application = new Application();
        $application->setCompanyName('Original Company');
        $application->setPosition('Developer');
        $application->setStatus(ApplicationStatus::APPLIED);
        $application->setUser($this->testUser);
        $this->entityManager->persist($application);
        $this->entityManager->flush();
        $applicationId = $application->getId();

        $this->client->request(
            'PUT',
            "/api/applications/{$applicationId}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token
            ],
            json_encode([
                'companyName' => 'Updated Company',
                'position' => 'Senior Developer',
                'status' => ApplicationStatus::INTERVIEW->value
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Updated Company', $response['companyName']);
        $this->assertEquals('Senior Developer', $response['position']);
    }

    public function testChangeApplicationStatus(): void
    {
        $application = new Application();
        $application->setCompanyName('Test Company');
        $application->setPosition('Developer');
        $application->setStatus(ApplicationStatus::APPLIED);
        $application->setUser($this->testUser);
        $this->entityManager->persist($application);
        $this->entityManager->flush();
        $applicationId = $application->getId();

        $this->client->request(
            'PATCH',
            "/api/applications/{$applicationId}/status",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token
            ],
            json_encode([
                'status' => ApplicationStatus::INTERVIEW->value
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(ApplicationStatus::INTERVIEW->value, $response['status']);
    }

    public function testDeleteApplication(): void
    {
        $application = new Application();
        $application->setCompanyName('Test Company');
        $application->setPosition('Developer');
        $application->setStatus(ApplicationStatus::APPLIED);
        $application->setUser($this->testUser);
        $this->entityManager->persist($application);
        $this->entityManager->flush();
        $applicationId = $application->getId();

        $this->client->request(
            'DELETE',
            "/api/applications/{$applicationId}",
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }
}
