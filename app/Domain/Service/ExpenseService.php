<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private readonly LoggerInterface $logger,
    ) {}

    public function list(User $user, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        $criteria = [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ];

        $offset = ($pageNumber - 1) * $pageSize;

        $expenses = $this->expenses->findBy($criteria, $offset, $pageSize);

        $totalCount = $this->expenses->countBy($criteria);

        $totalPages = ceil($totalCount / $pageSize);

        $years = $this->expenses->listExpenditureYears($user);

        return [
            'expenses' => $expenses,
            'totalCount' => $totalCount,
            'currentPage' => $pageNumber,
            'totalPages' => $totalPages,
            'pageSize' => $pageSize,
            'years' => $years,
            'year' => $year,
            'month' => $month,
        ];
    }

    public function create(
        User $user,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        $this->validateExpenseData($amount, $description, $date, $category);

        $amountCents = (int)($amount * 100);

        $expense = new Expense(null, $user->id, $date, $category, $amountCents, $description);
        $this->expenses->save($expense);
    }

    private function validateExpenseData(float $amount, string $description, DateTimeImmutable $date, string $category): void
    {
        // Date is not in future
        $today = new DateTimeImmutable();
        if ($date > $today) {
            throw new \InvalidArgumentException('Date cannot be in the future');
        }

        // Amount is positive
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        // Description is not empty
        if (empty(trim($description))) {
            throw new \InvalidArgumentException('Description cannot be empty');
        }

        // Category is selected
        if (empty(trim($category))) {
            throw new \InvalidArgumentException('Category must be selected');
        }
    }

    public function update(
        Expense $expense,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        // Validate inputs
        $this->validateExpenseData($amount, $description, $date, $category);

        $amountCents = (int)($amount * 100);

        // Update expense
        $expense->date = $date;
        $expense->category = $category;
        $expense->amountCents = $amountCents;
        $expense->description = $description;

        // Save updated expense
        $this->expenses->save($expense);
    }

    public function importFromCsv(User $user, UploadedFileInterface $csvFile): int
    {
        $pdo = $this->expenses->getPdo();

        $pdo->beginTransaction();

        try {
            // Open CSV file
            $tempFile = $csvFile->getStream()->getMetadata('uri');

            $handle = fopen($tempFile, 'r');
            if ($handle === false) {
                throw new \RuntimeException('Could not open CSV file');
            }

            $firstRow = fgetcsv($handle);
            if ($firstRow === false) {
                fclose($handle);
                throw new \RuntimeException('CSV file is empty');
            }

            rewind($handle);
            $columnMap = [
                'date' => 0,
                'amount' => 1,
                'description' => 2,
                'category' => 3
            ];

            // Process each row
            $importedCount = 0;
            $skippedCount = 0;
            $lineNumber = 0;

            // Track processed rows to avoid duplicates
            $processedRows = [];

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                // Skip empty rows
                if (count($row) <= 1 && empty($row[0])) {
                    continue;
                }

                // Skip rows with insufficient columns
                if (count($row) < 4) {
                    $this->logger->warning("Line $lineNumber: Insufficient columns, skipping");
                    $skippedCount++;
                    continue;
                }

                try {
                    $dateStr = $row[$columnMap['date']] ?? '';
                    $amountStr = $row[$columnMap['amount']] ?? '';
                    $description = $row[$columnMap['description']] ?? '';
                    $category = $row[$columnMap['category']] ?? '';

                    // Create unique key for row to detect duplicates
                    $rowKey = md5($dateStr . $amountStr . $description . $category);
                    if (isset($processedRows[$rowKey])) {
                        $this->logger->warning("Line $lineNumber: Duplicate row, skipping");
                        $skippedCount++;
                        continue;
                    }

                    // Map categories from CSV to our internal categories
                    $mappedCategory = $this->mapCategory($category);
                    if ($mappedCategory === null) {
                        $this->logger->warning("Line $lineNumber: Unknown category '$category', skipping");
                        $skippedCount++;
                        continue;
                    }

                    // Validate and convert data
                    if (empty($dateStr) || empty($mappedCategory) || empty($amountStr)) {
                        throw new \InvalidArgumentException("Line $lineNumber: Missing required data");
                    }

                    // Parse date
                    try {
                        $date = new DateTimeImmutable($dateStr);
                    } catch (\Exception $e) {
                        throw new \InvalidArgumentException("Line $lineNumber: Invalid date format");
                    }

                    // Parse amount
                    $amount = str_replace(',', '.', $amountStr);
                    $amount = filter_var($amount, FILTER_VALIDATE_FLOAT);
                    if ($amount === false) {
                        throw new \InvalidArgumentException("Line $lineNumber: Invalid amount format");
                    }

                    // Validate data
                    $this->validateExpenseData($amount, $description, $date, $mappedCategory);

                    $amountCents = (int)($amount * 100);

                    // Create and save expense
                    $expense = new Expense(null, $user->id, $date, $mappedCategory, $amountCents, $description);
                    $this->expenses->save($expense);

                    // Mark row as processed
                    $processedRows[$rowKey] = true;
                    $importedCount++;
                } catch (\InvalidArgumentException $e) {
                    // Log error and continue processing
                    $this->logger->warning($e->getMessage());
                    $skippedCount++;
                }
            }

            fclose($handle);

            // Log import completion
            $this->logger->info("CSV import completed: $importedCount rows imported, $skippedCount rows skipped");

            // Commit transaction
            $pdo->commit();

            return $importedCount;
        } catch (\Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $this->logger->error("CSV import failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function mapCategory(string $category): ?string
    {
        $category = trim($category);

        // Skip empty categories
        if (empty($category)) {
            return null;
        }

        // Direct mapping for categories in CSV
        $categoryMap = [
            'Groceries' => 'groceries',
            'Transport' => 'transport',
            'Entertainment' => 'entertainment',
            'Utilities' => 'utilities',
        ];

        // Check for direct match
        if (isset($categoryMap[$category])) {
            return $categoryMap[$category];
        }

        // Check for case-insensitive
        foreach ($categoryMap as $externalCat => $internalCat) {
            if (strcasecmp($externalCat, $category) === 0) {
                return $internalCat;
            }
        }

        // Skip unknown categories
        return null;
    }

    public function findById(int $id): ?Expense
    {
        return $this->expenses->find($id);
    }

    public function delete(int $id): void
    {
        $this->expenses->delete($id);
    }
}
