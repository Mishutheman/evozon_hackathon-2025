<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function register(string $username, string $password, string $password_confirm): User
    {
        // Check if user with same username exists
        $existingUser = $this->users->findByUsername($username);
        if ($existingUser !== null) {
            throw new \InvalidArgumentException('Username already exists');
        }

        // Validate username length (>= 4 chars)
        if (strlen($username) < 4) {
            throw new \InvalidArgumentException('Username must be at least 4 characters long');
        }

        // Validate password (>= 8 chars, 1 number)
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters long');
        }

        if (!preg_match('/[0-9]/', $password)) {
            throw new \InvalidArgumentException('Password must contain at least one number');
        }

        // Check if password and confirmation match
        if($password_confirm !== $password) {
            throw new \InvalidArgumentException('Password and confirmation password do not match');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);


        $user = new User(null, $username, $passwordHash, new \DateTimeImmutable());
        $this->users->save($user);

        return $user;
    }

    public function attempt(string $username, string $password): bool
    {

        if (empty($username) || empty($password)) {
            return false;
        }
        $user = $this->users->findByUsername($username);

        if ($user === null || !password_verify($password, $user->passwordHash)) {
            return false;
        }

        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;

        return true;
    }
}
