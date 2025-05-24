<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(mixed $id): ?User
    {
        $query = 'SELECT * FROM users WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return new User(
            $data['id'],
            $data['username'],
            $data['password_hash'],
            new DateTimeImmutable($data['created_at']),
        );
    }

    /**
     * @throws Exception
     */
    public function findByUsername(string $username): ?User
    {
        $query = 'SELECT * FROM users WHERE username = :username';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['username' => $username]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return new User(
            $data['id'],
            $data['username'],
            $data['password_hash'],
            new DateTimeImmutable($data['created_at']),
        );
    }

    public function save(User $user): void
    {
        if ($user->id === null) {
            $query = 'INSERT INTO users (username, password_hash, created_at) VALUES (:username, :password_hash, :created_at)';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'username' => $user->username,
                'password_hash' => $user->passwordHash,
                'created_at' => $user->createdAt->format('Y-m-d H:i:s'),
            ]);

            $user->id = (int) $this->pdo->lastInsertId();
        } else {
            $query = 'UPDATE users SET username = :username, password_hash = :password_hash WHERE id = :id';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'id' => $user->id,
                'username' => $user->username,
                'password_hash' => $user->passwordHash,
            ]);
        }
    }
}
