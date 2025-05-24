<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;

class MonthlySummaryService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function computeTotalExpenditure(User $user, int $year, int $month): float
    {
        $criteria = [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ];

        return $this->expenses->sumAmounts($criteria);
    }

    public function computePerCategoryTotals(User $user, int $year, int $month): array
    {
        $criteria = [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ];

        $totals = $this->expenses->sumAmountsByCategory($criteria);

        foreach ($totals as $category => $data) {
            $totals[$category]['value'] = $data['value'] / 100;
        }

        return $totals;
    }

    public function computePerCategoryAverages(User $user, int $year, int $month): array
    {
        $criteria = [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ];

        $averages = $this->expenses->averageAmountsByCategory($criteria);

        foreach ($averages as $category => $data) {
            $averages[$category]['value'] = $data['value'] / 100;
        }

        return $averages;
    }

    public function getAvailableYears(User $user): array
    {
        return $this->expenses->listExpenditureYears($user);
    }
}
