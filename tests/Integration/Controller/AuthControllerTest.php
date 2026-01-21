<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testRegisterWithValidData(): void
    {
        $this->client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test' . uniqid() . '@example.com',
                'password' => 'password123',
                'firstName' => 'John',
                'lastName' => 'Doe'
            ])
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $content = $this->client->getResponse()->getContent();
        
        if ($statusCode !== Response::HTTP_CREATED) {
            $this->fail("Expected 201, got {$statusCode}. Response: " . $content);
        }
        
        $this->assertEquals(Response::HTTP_CREATED, $statusCode);
        
        $response = json_decode($content, true);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('refreshToken', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertEquals('John', $response['user']['firstName']);
        $this->assertFalse($response['user']['isVerified']);
    }

    public function testRegisterWithInvalidData(): void
    {
        $this->client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'invalid-email',
                'password' => '123',
                'firstName' => '',
                'lastName' => ''
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
    }

    public function testRegisterWithDuplicateEmail(): void
    {
        $email = 'duplicate' . uniqid() . '@example.com';
        
        $this->client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'password123',
                'firstName' => 'John',
                'lastName' => 'Doe'
            ])
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'password123',
                'firstName' => 'Jane',
                'lastName' => 'Doe'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('already exists', $response['error']);
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'nonexistent@example.com',
                'password' => 'wrongpassword'
            ])
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testGetMeWithoutAuthentication(): void
    {
        $this->client->request('GET', '/api/me');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }
}
