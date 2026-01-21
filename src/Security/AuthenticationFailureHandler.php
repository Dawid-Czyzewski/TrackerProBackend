<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $errorMessage = 'Invalid credentials';
        $statusCode = Response::HTTP_UNAUTHORIZED;

        $accountStatusException = null;
        if ($exception instanceof CustomUserMessageAccountStatusException) {
            $accountStatusException = $exception;
        } elseif ($exception->getPrevious() instanceof CustomUserMessageAccountStatusException) {
            $accountStatusException = $exception->getPrevious();
        }

        if ($accountStatusException) {
            $errorMessage = $accountStatusException->getMessageKey();
            $statusCode = Response::HTTP_FORBIDDEN;
        }

        $response = new JWTAuthenticationFailureResponse($errorMessage, $statusCode);
        
        $response->setData([
            'error' => $errorMessage,
            'message' => $accountStatusException ? $accountStatusException->getMessage() : $errorMessage,
        ]);

        $event = new AuthenticationFailureEvent($exception, $response);
        $this->dispatcher->dispatch($event, Events::AUTHENTICATION_FAILURE);

        return $event->getResponse();
    }
}
