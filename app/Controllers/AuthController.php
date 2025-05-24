<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(
        Twig $view,
        private AuthService $authService,
        private LoggerInterface $logger,
    ) {
        parent::__construct($view);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        // TODO: you also have a logger service that you can inject and use anywhere; file is var/app.log
        $this->logger->info('Register page requested');

        return $this->render($response, 'auth/register.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $confPass = $data['password_confirm'] ?? '';
        $errors = [];

        try {
            $this->authService->register($username, $password, $confPass);
            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (\InvalidArgumentException $e) {
            // Determine which field has the error
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'Username') !== false) {
                $errors['username'] = $errorMessage;
            } else {
                $errors['password'] = $errorMessage;
            }

            // Re-render the register form with errors
            return $this->render(
                $response, 
                'auth/register.twig', 
                [
                    'username' => $username,
                    'password' => $password,
                    'errors' => $errors
                ]
            );
        }
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        // Attempt to authenticate the user
        if ($this->authService->attempt($username, $password)) {
            // On success, redirect to Dashboard page
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        // On failure, render Login page with error message
        return $this->render(
            $response, 
            'auth/login.twig', 
            [
                'username' => $username,
                'error' => 'Invalid username or password'
            ]
        );
    }

    public function logout(Request $request, Response $response): Response
    {
        // Clear session data
        $_SESSION = [];

        // Destroy the session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        // Redirect to login page
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
