<?php

namespace App\Service;

use App\DTO\RegisterDTO;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function register(RegisterDTO $dto): User
    {
        $user = new User();
        $user->setEmail($dto->email);
        $user->setFirstName($dto->firstName);
        $user->setLastName($dto->lastName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        
        $verificationToken = bin2hex(random_bytes(32));
        $user->setVerificationToken($verificationToken);
        $user->setIsVerified(false);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
