<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

final class User
{
    public function __construct(
        public ?int $id,
        public string $username,
        public string $passwordHash,
        public DateTimeImmutable $createdAt,
    ) {}
}
