<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;

interface UserRepositoryInterface
{
    // TODO: please review the list of methods below. Keep in mind these are just provided for guidance,
    // TODO: and there is no requirement to keep them as they are. Feel free to adapt to your own implementation.

    public function findByUsername(string $username): ?User;

    public function find(mixed $id): ?User;

    public function save(User $user): void;
}
