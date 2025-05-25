<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;

class AlertGenerator
{
    public function __construct(
        private readonly CategoryBudgetService $catBudgetServ,
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function generate(User $user, int $year, int $month): array
    {
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('n');

        if ($year !== $currentYear || $month !== $currentMonth) {
            return [];
        }

        $criteria = [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ];

        // Get category totals per month
        $categoryTotals = $this->expenses->sumAmountsByCategory($criteria);

        $alerts = [];

        // Check each category against budget
        foreach ($categoryTotals as $category => $data) {
            $amountEuros = $data['value'] / 100;
            $budget = $this->catBudgetServ->getBudgetForCategory(strtolower($category));

            // Skip if no budget defined
            if ($budget === null) {
                continue;
            }

            // Generate alert if spending exceeds budget
            if ($amountEuros > $budget) {
                $exceededBy = $amountEuros - $budget;
                $alerts[] = [
                    'category' => $category,
                    'budget' => $budget,
                    'spent' => $amountEuros,
                    'exceededBy' => $exceededBy,
                    'message' => "⚠ {$category} budget exceeded by " . number_format($exceededBy, 2) . " €"
                ];
            }
        }

        return $alerts;
    }
}
