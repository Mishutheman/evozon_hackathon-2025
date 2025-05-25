<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(int $id): ?Expense
    {
        $query = 'SELECT * FROM expenses WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return $this->createExpenseFromData($data);
    }

    public function save(Expense $expense): void
    {
        if ($expense->id === null) {
            // Insert new expense
            $query = 'INSERT INTO expenses (user_id, date, category, amount_cents, description) 
                      VALUES (:user_id, :date, :category, :amount_cents, :description)';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'user_id' => $expense->userId,
                'date' => $expense->date->format('Y-m-d'),
                'category' => $expense->category,
                'amount_cents' => $expense->amountCents,
                'description' => $expense->description,
            ]);

            // Set ID of expense object to the last inserted ID
            $expense->id = (int) $this->pdo->lastInsertId();
        } else {
            // Update existing expense
            $query = 'UPDATE expenses 
                      SET date = :date, category = :category, amount_cents = :amount_cents, description = :description 
                      WHERE id = :id AND user_id = :user_id';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'id' => $expense->id,
                'user_id' => $expense->userId,
                'date' => $expense->date->format('Y-m-d'),
                'category' => $expense->category,
                'amount_cents' => $expense->amountCents,
                'description' => $expense->description,
            ]);
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id=?');
        $statement->execute([$id]);
    }

    public function findBy(array $criteria, int $from, int $limit): array
    {
        $query = 'SELECT * FROM expenses WHERE 1=1';
        $params = [];

        // Add criteria to query
        if (isset($criteria['user_id'])) {
            $query .= ' AND user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['year']) && isset($criteria['month'])) {
            $startDate = sprintf('%04d-%02d-01', $criteria['year'], $criteria['month']);
            $endDate = date('Y-m-t', strtotime($startDate));
            $query .= ' AND date BETWEEN :start_date AND :end_date';
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }

        // Add sorting
        $query .= ' ORDER BY date DESC';

        // Add pagination
        $query .= ' LIMIT :limit OFFSET :offset';
        $params['limit'] = $limit;
        $params['offset'] = $from;

        $statement = $this->pdo->prepare($query);

        // Bind parameters
        foreach ($params as $key => $value) {
            if ($key === 'limit' || $key === 'offset') {
                $statement->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $statement->bindValue($key, $value);
            }
        }

        $statement->execute();
        $data = $statement->fetchAll();

        $expenses = [];
        foreach ($data as $row) {
            $expenses[] = $this->createExpenseFromData($row);
        }

        return $expenses;
    }


    public function countBy(array $criteria): int
    {
        $query = 'SELECT COUNT(*) FROM expenses WHERE 1=1';
        $params = [];


        // Add criteria to query
        if (isset($criteria['user_id'])) {
            $query .= ' AND user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['year']) && isset($criteria['month'])) {
            $startDate = sprintf('%04d-%02d-01', $criteria['year'], $criteria['month']);
            $endDate = date('Y-m-t', strtotime($startDate));
            $query .= ' AND date BETWEEN :start_date AND :end_date';
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }

        $statement = $this->pdo->prepare($query);

        // Bind parameters
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function listExpenditureYears(User $user): array
    {
        $query = 'SELECT DISTINCT strftime("%Y", date) as year FROM expenses WHERE user_id = :user_id ORDER BY year DESC';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['user_id' => $user->id]);

        $years = $statement->fetchAll(PDO::FETCH_COLUMN);

        // Include current year even if no expenses yet
        $currentYear = date('Y');
        if (!in_array($currentYear, $years)) {
            $years[] = $currentYear;
            sort($years, SORT_NUMERIC);
            $years = array_reverse($years);
        }

        return $years;
    }

    public function sumAmountsByCategory(array $criteria): array
    {
        $query = 'SELECT category, SUM(amount_cents) as total FROM expenses WHERE 1=1';
        $params = [];

        // Add criteria to query
        if (isset($criteria['user_id'])) {
            $query .= ' AND user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['year']) && isset($criteria['month'])) {
            $startDate = sprintf('%04d-%02d-01', $criteria['year'], $criteria['month']);
            $endDate = date('Y-m-t', strtotime($startDate));
            $query .= ' AND date BETWEEN :start_date AND :end_date';
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }

        $query .= ' GROUP BY category ORDER BY total DESC';

        $statement = $this->pdo->prepare($query);

        // Bind parameters
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();

        $result = [];
        $data = $statement->fetchAll();
        $totalSum = 0;

        // Calculate total sum for percentage calculation
        foreach ($data as $row) {
            $totalSum += (int) $row['total'];
        }

        // Build result array
        foreach ($data as $row) {
            $total = (int) $row['total'];
            $percentage = $totalSum > 0 ? round(($total / $totalSum) * 100) : 0;
            $result[$row['category']] = [
                'value' => $total,
                'percentage' => $percentage
            ];
        }

        return $result;
    }

    public function averageAmountsByCategory(array $criteria): array
    {
        $query = 'SELECT category, AVG(amount_cents) as average, COUNT(*) as count FROM expenses WHERE 1=1';
        $params = [];

        // Add criteria to query
        if (isset($criteria['user_id'])) {
            $query .= ' AND user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['year']) && isset($criteria['month'])) {
            $startDate = sprintf('%04d-%02d-01', $criteria['year'], $criteria['month']);
            $endDate = date('Y-m-t', strtotime($startDate));
            $query .= ' AND date BETWEEN :start_date AND :end_date';
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }

        $query .= ' GROUP BY category ORDER BY average DESC';

        $statement = $this->pdo->prepare($query);

        // Bind parameters
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();

        $result = [];
        $data = $statement->fetchAll();
        $totalAverage = 0;
        $totalCount = 0;

        // Calculate total average for percentage calculation
        foreach ($data as $row) {
            $totalAverage += (float) $row['average'] * (int) $row['count'];
            $totalCount += (int) $row['count'];
        }

        $overallAverage = $totalCount > 0 ? $totalAverage / $totalCount : 0;

        // Build result array
        foreach ($data as $row) {
            $average = (float) $row['average'];
            $percentage = $overallAverage > 0 ? round(($average / $overallAverage) * 100) : 0;
            $result[$row['category']] = [
                'value' => $average,
                'percentage' => $percentage
            ];
        }

        return $result;
    }

    public function sumAmounts(array $criteria): float
    {
        $query = 'SELECT SUM(amount_cents) as total FROM expenses WHERE 1=1';
        $params = [];

        // Add criteria to query
        if (isset($criteria['user_id'])) {
            $query .= ' AND user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['year']) && isset($criteria['month'])) {
            $startDate = sprintf('%04d-%02d-01', $criteria['year'], $criteria['month']);
            $endDate = date('Y-m-t', strtotime($startDate));
            $query .= ' AND date BETWEEN :start_date AND :end_date';
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }

        $statement = $this->pdo->prepare($query);

        // Bind parameters
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();

        $total = $statement->fetchColumn();

        return (float) $total / 100;
    }

    /**
     * @throws Exception
     */
    private function createExpenseFromData(mixed $data): Expense
    {
        return new Expense(
            $data['id'],
            $data['user_id'],
            new DateTimeImmutable($data['date']),
            $data['category'],
            $data['amount_cents'],
            $data['description'],
        );
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
