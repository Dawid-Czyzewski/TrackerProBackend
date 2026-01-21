<?php

namespace App\Tests\Integration;

use App\Entity\Application;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class EndToEndTest extends WebTestCase
{
    private $client;
    private ?EntityManagerInterface $entityManager = null;
    private ?User $testUser = null;
    private ?string $authToken = null;
    private ?int $applicationId = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        if ($this->entityManager && $this->entityManager->isOpen()) {
            if ($this->applicationId) {
                $application = $this->entityManager->getRepository(Application::class)->find($this->applicationId);
                if ($application) {
                    $this->entityManager->remove($application);
                }
            }
            
            if ($this->testUser) {
                $user = $this->entityManager->getRepository(User::class)->find($this->testUser->getId());
                if ($user) {
                    $refreshTokenRepo = $this->entityManager->getRepository(\App\Entity\RefreshToken::class);
                    $refreshTokens = $refreshTokenRepo->findBy(['user' => $user]);
                    foreach ($refreshTokens as $refreshToken) {
                        $this->entityManager->remove($refreshToken);
                    }
                    
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
            }
        }
        parent::tearDown();
    }

    public function testCompleteUserFlow(): void
    {
        $uniqueEmail = 'e2e_test_' . uniqid() . '@example.com';
        $password = 'TestPassword123!';
        $firstName = 'E2E';
        $lastName = 'Test';

        $this->testUserRegistration($uniqueEmail, $password, $firstName, $lastName);
        $this->testEmailVerification($uniqueEmail);
        $this->testUserLogin($uniqueEmail, $password);
        $this->testCreateApplication();
        $this->testGetApplication();
        $this->testChangeApplicationStatus();
        $this->testGetApplicationWithHistory();
        $this->testUpdateApplication();
        $this->testGetApplicationStats();
        $this->testDeleteApplication();
    }

    private function testUserRegistration(string $email, string $password, string $firstName, string $lastName): void
    {
        $this->client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => $password,
                'firstName' => $firstName,
                'lastName' => $lastName
            ])
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('refreshToken', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertEquals($email, $response['user']['email']);
        $this->assertEquals($firstName, $response['user']['firstName']);
        $this->assertEquals($lastName, $response['user']['lastName']);
        $this->assertFalse($response['user']['isVerified']);

        $this->testUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($this->testUser);
        $this->assertNotNull($this->testUser->getVerificationToken());
    }

    private function testEmailVerification(string $email): void
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $verificationToken = $user->getVerificationToken();
        $this->assertNotNull($verificationToken);

        $this->client->request('GET', '/api/verify-email?token=' . $verificationToken);

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertStringContainsString('verified', strtolower($response['message']));
        $this->assertTrue($response['user']['isVerified']);

        $this->entityManager->clear();
        $verifiedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        $this->assertNotNull($verifiedUser);
        $this->assertTrue($verifiedUser->isVerified());
        $this->assertNull($verifiedUser->getVerificationToken());
    }

    private function testUserLogin(string $email, string $password): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => $password
            ])
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $content = $this->client->getResponse()->getContent();
        
        if ($statusCode !== Response::HTTP_OK) {
            $this->fail("Expected 200, got {$statusCode}. Response: " . $content);
        }
        
        $this->assertEquals(Response::HTTP_OK, $statusCode);
        
        $response = json_decode($content, true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('token', $response);
        if (isset($response['refreshToken'])) {
            $this->assertArrayHasKey('refreshToken', $response);
        }
        if (isset($response['user'])) {
            $this->assertArrayHasKey('user', $response);
            $this->assertEquals($email, $response['user']['email']);
        }

        $this->authToken = $response['token'];
    }

    private function testCreateApplication(): void
    {
        $this->client->request(
            'POST',
            '/api/applications',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->authToken
            ],
            json_encode([
                'companyName' => 'Test Company E2E',
                'position' => 'Senior Developer',
                'platform' => 'LinkedIn',
                'status' => 'applied',
                'appliedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ])
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('Test Company E2E', $response['companyName']);
        $this->assertEquals('Senior Developer', $response['position']);
        $this->assertEquals('LinkedIn', $response['platform']);
        $this->assertEquals('applied', $response['status']);
        $this->assertArrayHasKey('appliedAt', $response);

        $this->applicationId = $response['id'];
    }

    private function testGetApplication(): void
    {
        $this->client->request(
            'GET',
            '/api/applications/' . $this->applicationId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->authToken]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($this->applicationId, $response['id']);
        $this->assertEquals('Test Company E2E', $response['companyName']);
        $this->assertEquals('applied', $response['status']);
    }

    private function testChangeApplicationStatus(): void
    {
        $this->client->request(
            'PATCH',
            '/api/applications/' . $this->applicationId . '/status',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->authToken
            ],
            json_encode(['status' => 'recruitment_task'])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('recruitment_task', $response['status']);

        $this->client->request(
            'PATCH',
            '/api/applications/' . $this->applicationId . '/status',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->authToken
            ],
            json_encode(['status' => 'interview'])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('interview', $response['status']);
    }

    private function testGetApplicationWithHistory(): void
    {
        $this->client->request(
            'GET',
            '/api/applications/' . $this->applicationId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->authToken]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('statusHistory', $response);
        $this->assertIsArray($response['statusHistory']);
        $this->assertGreaterThanOrEqual(2, count($response['statusHistory']));

        $statuses = array_column($response['statusHistory'], 'newStatus');
        $this->assertContains('recruitment_task', $statuses);
        $this->assertContains('interview', $statuses);
    }

    private function testUpdateApplication(): void
    {
        $this->client->request(
            'PUT',
            '/api/applications/' . $this->applicationId,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->authToken
            ],
            json_encode([
                'companyName' => 'Updated Company Name',
                'position' => 'Lead Developer',
                'platform' => 'Indeed',
                'status' => 'interview'
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Updated Company Name', $response['companyName']);
        $this->assertEquals('Lead Developer', $response['position']);
        $this->assertEquals('Indeed', $response['platform']);
        $this->assertEquals('interview', $response['status']);
    }

    private function testGetApplicationStats(): void
    {
        $this->client->request(
            'GET',
            '/api/applications/stats',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->authToken]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('weekly', $response);
        $this->assertArrayHasKey('monthly', $response);
        $this->assertArrayHasKey('latest', $response);
        $this->assertIsInt($response['weekly']);
        $this->assertIsInt($response['monthly']);
        $this->assertIsArray($response['latest']);
    }

    private function testDeleteApplication(): void
    {
        $this->client->request(
            'DELETE',
            '/api/applications/' . $this->applicationId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->authToken]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertStringContainsString('deleted', strtolower($response['message']));

        $this->client->request(
            'GET',
            '/api/applications/' . $this->applicationId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->authToken]
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        
        $this->applicationId = null;
    }
}
