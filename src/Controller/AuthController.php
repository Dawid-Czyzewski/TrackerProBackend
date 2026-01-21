<?php

namespace App\Controller;

use App\DTO\RegisterDTO;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use App\Service\UserService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ValidatorInterface $validator,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $dto = new RegisterDTO(
            email: $data['email'] ?? null,
            password: $data['password'] ?? null,
            firstName: $data['firstName'] ?? null,
            lastName: $data['lastName'] ?? null
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->userService->register($dto);
            
            $frontendUrl = $this->parameterBag->has('app.frontend_url') 
                && !empty($this->parameterBag->get('app.frontend_url'))
                ? $this->parameterBag->get('app.frontend_url')
                : ($request->headers->get('Origin') ?? 'http://localhost:3000');
            
            $this->emailVerificationService->sendVerificationEmail($user, $frontendUrl);
            
            $token = $this->jwtManager->create($user);
            $refreshToken = $this->createRefreshToken($user);

            return $this->json([
                'token' => $token,
                'refreshToken' => $refreshToken->getToken(),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'isVerified' => $user->isVerified(),
                ],
                'message' => 'Registration successful. Please check your email to verify your account.',
            ], Response::HTTP_CREATED);
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(['error' => 'User with this email already exists'], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Registration error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $this->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isVerified()) {
            return $this->json(['error' => 'account_not_verified'], Response::HTTP_FORBIDDEN);
        }

        $token = $this->jwtManager->create($user);
        $refreshToken = $this->createRefreshToken($user);

        return $this->json([
            'token' => $token,
            'refreshToken' => $refreshToken->getToken(),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ],
        ]);
    }

    #[Route('/refresh', name: 'api_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $refreshTokenValue = $data['refreshToken'] ?? null;

        if (!$refreshTokenValue) {
            return $this->json(['error' => 'Refresh token required'], Response::HTTP_BAD_REQUEST);
        }

        $refreshTokenRepo = $this->entityManager->getRepository(RefreshToken::class);
        $refreshToken = $refreshTokenRepo->findValidByToken($refreshTokenValue);

        if (!$refreshToken) {
            return $this->json(['error' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $refreshToken->getUser();
        $token = $this->jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'refreshToken' => $refreshToken->getToken(),
        ]);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'isVerified' => $user->isVerified(),
        ]);
    }

    #[Route('/verify-email', name: 'api_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $token = $request->query->get('token');

        if (!$token) {
            return $this->json(['error' => 'Verification token is required'], Response::HTTP_BAD_REQUEST);
        }

        $userRepo = $this->entityManager->getRepository(User::class);
        $user = $userRepo->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return $this->json(['error' => 'Invalid verification token'], Response::HTTP_BAD_REQUEST);
        }

        if ($user->isVerified()) {
            return $this->json(['message' => 'Email is already verified'], Response::HTTP_OK);
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Email verified successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }

    #[Route('/test/verify-user/{email}', name: 'api_test_verify_user', methods: ['GET'])]
    public function testVerifyUser(string $email): JsonResponse
    {
        $env = $this->parameterBag->get('kernel.environment');
        if ($env !== 'test' && $env !== 'dev') {
            return $this->json(['error' => 'Not available in production'], Response::HTTP_FORBIDDEN);
        }

        $userRepo = $this->entityManager->getRepository(User::class);
        $user = $userRepo->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if ($user->isVerified()) {
            return $this->json(['message' => 'User is already verified'], Response::HTTP_OK);
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'User verified successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }

    private function createRefreshToken(User $user): RefreshToken
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user);
        $refreshToken->setToken(bin2hex(random_bytes(32)));

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $refreshToken;
    }
}
