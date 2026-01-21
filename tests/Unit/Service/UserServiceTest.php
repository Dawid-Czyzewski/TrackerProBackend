<?php

namespace App\Tests\Unit\Service;

use App\DTO\RegisterDTO;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserServiceTest extends TestCase
{
    private UserService $userService;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        
        $this->userService = new UserService(
            $this->entityManager,
            $this->passwordHasher
        );
    }

    public function testRegisterCreatesUserWithCorrectData(): void
    {
        $dto = new RegisterDTO(
            email: 'test@example.com',
            password: 'password123',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($user) {
                return $user instanceof User
                    && $user->getEmail() === 'test@example.com'
                    && $user->getFirstName() === 'John'
                    && $user->getLastName() === 'Doe'
                    && $user->getPassword() === 'hashed_password'
                    && !$user->isVerified()
                    && $user->getVerificationToken() !== null;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $user = $this->userService->register($dto);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());
        $this->assertFalse($user->isVerified());
        $this->assertNotNull($user->getVerificationToken());
    }

    public function testRegisterGeneratesVerificationToken(): void
    {
        $dto = new RegisterDTO(
            email: 'test@example.com',
            password: 'password123',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->passwordHasher
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->method('flush');

        $user = $this->userService->register($dto);

        $this->assertNotNull($user->getVerificationToken());
        $this->assertEquals(64, strlen($user->getVerificationToken()));
    }
}
