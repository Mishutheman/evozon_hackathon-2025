<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Entity\User;
use App\Domain\Service\AlertGenerator;
use App\Domain\Service\CategoryBudgetService;
use App\Domain\Service\MonthlySummaryService;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardController extends BaseController
{
    public function __construct(
        Twig $view,
        private readonly MonthlySummaryService $monthSummService,
        private readonly AlertGenerator $alertGenerator,
        private readonly CategoryBudgetService $catBudgetService,
    )
    {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        // Get the user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = new User(
            $userId, 
            $_SESSION['username'] ?? '', 
            '', 
            new DateTimeImmutable()
        );

        // Get query parameters for year and month
        $queryParams = $request->getQueryParams();
        $year = (int)($queryParams['year'] ?? date('Y'));
        $month = (int)($queryParams['month'] ?? date('n'));

        // Get available years for the selector
        $years = $this->monthSummService->getAvailableYears($user);

        // Overspending alerts for current month
        $alerts = $this->alertGenerator->generate($user, $year, $month);

        // Total expenditure for year/month
        $totalForMonth = $this->monthSummService->computeTotalExpenditure($user, $year, $month);

        // Category totals for year/month
        $totalsForCat = $this->monthSummService->computePerCategoryTotals($user, $year, $month);

        // Category averages for year/month
        $avgForCat = $this->monthSummService->computePerCategoryAverages($user, $year, $month);

        return $this->render($response, 'dashboard.twig', [
            'years' => $years,
            'year' => $year,
            'month' => $month,
            'alerts' => $alerts,
            'totalForMonth' => $totalForMonth,
            'totalsForCategories' => $totalsForCat,
            'averagesForCategories' => $avgForCat,
        ]);
    }
}
