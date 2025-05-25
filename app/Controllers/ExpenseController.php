<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\ExpenseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class ExpenseController extends BaseController
{
    private const PAGE_SIZE = 2;

    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }


        $user = new \App\Domain\Entity\User($userId, $_SESSION['username'] ?? '', '', new \DateTimeImmutable());

        // Parse request parameters
        $queryParams = $request->getQueryParams();
        $page = (int)($queryParams['page'] ?? 1);
        $pageSize = (int)($queryParams['pageSize'] ?? self::PAGE_SIZE);

        $year = (int)($queryParams['year'] ?? date('Y'));
        $month = (int)($queryParams['month'] ?? date('n'));

        // Get expenses list
        $result = $this->expenseService->list($user, $year, $month, $page, $pageSize);

        // Prepare next and previous page
        $queryString = http_build_query([
            'year' => $year,
            'month' => $month,
            'pageSize' => $pageSize,
        ]);

        $prevPageUrl = $page > 1 
            ? "/expenses?page=" . ($page - 1) . "&" . $queryString 
            : null;

        $nextPageUrl = $page < $result['totalPages'] 
            ? "/expenses?page=" . ($page + 1) . "&" . $queryString 
            : null;

        $categories = [
            'groceries' => 'Groceries',
            'utilities' => 'Utilities',
            'transport' => 'Transport',
            'entertainment' => 'Entertainment'
        ];

        // Get flash message from session
        $flash = $_SESSION['flash'] ?? null;

        // Clear flash message from session
        if (isset($_SESSION['flash'])) {
            unset($_SESSION['flash']);
        }

        $totalPages = $result['totalPages'];

        return $this->render($response, 'expenses/index.twig', [
            'expenses' => $result['expenses'],
            'total' => $result['totalCount'],
            'page' => $page,
            'pageSize' => $pageSize,
            'prevPageUrl' => $prevPageUrl,
            'nextPageUrl' => $nextPageUrl,
            'totalPages' => $totalPages,
            'years' => $result['years'],
            'year' => $year,
            'month' => $month,
            'categories' => $categories,
            'flash' => $flash,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $categories = [
            'groceries' => 'Groceries',
            'utilities' => 'Utilities',
            'transport' => 'Transport',
            'entertainment' => 'Entertainment'
        ];

        $today = date('Y-m-d');

        return $this->render($response, 'expenses/create.twig', [
            'categories' => $categories,
            'date' => $today,
            'errors' => [],
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }


        $user = new \App\Domain\Entity\User($userId, $_SESSION['username'] ?? '', '', new \DateTimeImmutable());

        // Get form data
        $data = $request->getParsedBody();
        $date = $data['date'] ?? date('Y-m-d');
        $category = $data['category'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $description = $data['description'] ?? '';

        // Define expense categories for re-rendering the form if needed
        $categories = [
            'groceries' => 'Groceries',
            'utilities' => 'Utilities',
            'transport' => 'Transport',
            'entertainment' => 'Entertainment'
        ];

        try {
            // Create date object from the date string
            $dateObj = new \DateTimeImmutable($date);

            // Create the expense
            $this->expenseService->create($user, $amount, $description, $dateObj, $category);

            // Redirect to expenses list on success
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } catch (\InvalidArgumentException $e) {
            // Re-render the form with error message on fail
            return $this->render($response, 'expenses/create.twig', [
                'categories' => $categories,
                'date' => $date,
                'category' => $category,
                'amount' => $amount,
                'description' => $description,
                'errors' => ['message' => $e->getMessage()],
            ]);
        } catch (\Exception $e) {
            // Re-render with a generic error message for other exceptions
            return $this->render($response, 'expenses/create.twig', [
                'categories' => $categories,
                'date' => $date,
                'category' => $category,
                'amount' => $amount,
                'description' => $description,
                'errors' => ['message' => 'An error occurred while saving the expense.'],
            ]);
        }
    }

    public function edit(Request $request, Response $response, array $routeParams): Response
    {
        // Get the user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        // Get expense ID from route parameters
        $expenseId = (int)($routeParams['id'] ?? 0);
        if (!$expenseId) {
            // No valid ID provided, redirect to expenses list
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        // Find expense by ID
        $expense = $this->expenseService->findById($expenseId);

        // Check that expense exists and belongs to the current user
        if (!$expense || $expense->userId !== $userId) {
            // Redirect to expenses list
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        // Define expense categories
        $categories = [
            'groceries' => 'Groceries',
            'utilities' => 'Utilities',
            'transport' => 'Transport',
            'entertainment' => 'Entertainment',
            'housing' => 'Housing',
            'health' => 'Healthcare',
            'other' => 'Other',
        ];

        // Format date for form
        $formattedDate = $expense->date->format('Y-m-d');

        // Format amount for form
        $formattedAmount = $expense->amountCents / 100;

        return $this->render($response, 'expenses/edit.twig', [
            'expense' => $expense,
            'categories' => $categories,
            'date' => $formattedDate,
            'amount' => $formattedAmount,
            'errors' => [],
        ]);
    }

    public function update(Request $request, Response $response, array $routeParams): Response
    {
        // Get the user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        // Get expense ID from route parameters
        $expenseId = (int)($routeParams['id'] ?? 0);
        if (!$expenseId) {
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        // Find expense by ID
        $expense = $this->expenseService->findById($expenseId);

        // Check that expense exists and belongs to the user
        if (!$expense || $expense->userId !== $userId) {
            // Redirect to expenses list
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        // Get form data
        $data = $request->getParsedBody();
        $date = $data['date'] ?? date('Y-m-d');
        $category = $data['category'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $description = $data['description'] ?? '';

        // Define expense categories for re-rendering
        $categories = [
            'groceries' => 'Groceries',
            'utilities' => 'Utilities',
            'transport' => 'Transport',
            'entertainment' => 'Entertainment'
        ];

        try {
            // Create date object from the date string
            $dateObj = new \DateTimeImmutable($date);

            // Update expense
            $this->expenseService->update($expense, $amount, $description, $dateObj, $category);

            // Redirect to expenses list
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } catch (\InvalidArgumentException $e) {
            // Re-render the form with error message on failure
            return $this->render($response, 'expenses/edit.twig', [
                'expense' => $expense,
                'categories' => $categories,
                'date' => $date,
                'category' => $category,
                'amount' => $amount,
                'description' => $description,
                'errors' => ['message' => $e->getMessage()],
            ]);
        } catch (\Exception $e) {
            // Re-render with a generic error message on other errors
            return $this->render($response, 'expenses/edit.twig', [
                'expense' => $expense,
                'categories' => $categories,
                'date' => $date,
                'category' => $category,
                'amount' => $amount,
                'description' => $description,
                'errors' => ['message' => 'An error occurred while updating the expense.'],
            ]);
        }
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        // Get the user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        // Get expense ID from route parameters
        $expenseId = (int)($routeParams['id'] ?? 0);
        if (!$expenseId) {
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        // Find expense by ID
        $expense = $this->expenseService->findById($expenseId);

        // Check that expense exists and belongs to the current user
        if (!$expense || $expense->userId !== $userId) {
            // Redirect to expenses list
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'You do not have permission to delete this expense.'
            ];
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        // Delete expense
        $this->expenseService->delete($expenseId);

        // Redirect to expenses list
        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    public function import(Request $request, Response $response): Response
    {
        // Get user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        // Get user object
        $user = new \App\Domain\Entity\User($userId, $_SESSION['username'] ?? '', '', new \DateTimeImmutable());

        // Get uploaded files
        $uploadedFiles = $request->getUploadedFiles();
        $csvFile = $uploadedFiles['csv'] ?? null;

        // Check if file was uploaded
        if (!$csvFile || $csvFile->getError() !== UPLOAD_ERR_OK) {
            // Redirect to expenses list
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'No CSV file uploaded or upload error occurred'
            ];
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        try {
            // Import expenses from CSV
            $importedCount = $this->expenseService->importFromCsv($user, $csvFile);

            // Redirect to expenses list with success
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => "Successfully imported $importedCount expenses"
            ];
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } catch (\Exception $e) {
            // Redirect to expenses list with error
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'Error importing expenses: ' . $e->getMessage()
            ];
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }
    }
}
