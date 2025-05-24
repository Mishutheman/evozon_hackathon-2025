<?php

declare(strict_types=1);

namespace App\Domain\Service;

use mysql_xdevapi\Exception;

class CategoryBudgetService
{
    private array $categoryBudgets;

    public function __construct(string $categoryBudgetsJson = null)
    {
        $this->categoryBudgets = [];

        if ($categoryBudgetsJson) {
            try {
                $decodedBudgets = json_decode($categoryBudgetsJson, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decodedBudgets) && !empty($decodedBudgets)) {
                    $this->categoryBudgets = $decodedBudgets;
                }
            } catch (\JsonException $e) {
                throw new Exception();
            }
        }
    }

    public function getCategoryBudgets(): array
    {
        return $this->categoryBudgets;
    }

    public function getBudgetForCategory(string $category): ?float
    {
        $normalizedCategory = strtolower($category);
        return $this->categoryBudgets[$normalizedCategory] ?? null;
    }

    public function getCategories(): array
    {
        return array_keys($this->categoryBudgets);
    }

    public function getCategoryDisplayNames(): array
    {
        $displayNames = [];
        foreach ($this->categoryBudgets as $category => $budget) {
            $displayNames[$category] = ucfirst($category);
        }
        return $displayNames;
    }
}
