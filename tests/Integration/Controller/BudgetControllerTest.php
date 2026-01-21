<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Budget;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class BudgetControllerTest extends WebTestCase
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
        $this->testUser->setEmail('budgettest' . uniqid() . '@example.com');
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
            } catch (\Exception $e) {}
        }
        $this->entityManager = null;
        $this->client = null;
        $this->testUser = null;
        $this->token = null;
        parent::tearDown();
    }

    public function testGetBudgetRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/budget');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testGetBudgetCreatesBudgetIfNotExists(): void
    {
        $this->client->request(
            'GET',
            '/api/budget',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('balance', $response);
        $this->assertEquals('0.00', $response['balance']);
    }

    public function testAddTransaction(): void
    {
        $this->client->request(
            'GET',
            '/api/budget',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]
        );

        $this->client->request(
            'POST',
            '/api/budget/transactions',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token
            ],
            json_encode([
                'type' => 'deposit',
                'amount' => '100.00',
                'description' => 'Test deposit'
            ])
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('deposit', $response['type']);
        $this->assertEquals('100.00', $response['amount']);
    }

    public function testAddTransactionWithInvalidData(): void
    {
        $this->client->request(
            'POST',
            '/api/budget/transactions',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token
            ],
            json_encode([
                'type' => 'invalid_type',
                'amount' => '-10.00'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testGetTransactions(): void
    {
        $this->client->request(
            'GET',
            '/api/budget/transactions',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
    }

    public function testAddGoal(): void
    {
        $this->client->request(
            'POST',
            '/api/budget/goals',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token
            ],
            json_encode([
                'name' => 'Test Goal',
                'targetAmount' => '500.00'
            ])
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Test Goal', $response['name']);
        $this->assertEquals('500.00', $response['targetAmount']);
    }
}
