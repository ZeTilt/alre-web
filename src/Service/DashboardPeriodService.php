<?php

namespace App\Service;

use App\Repository\CompanyRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class DashboardPeriodService
{
    public const PERIOD_CURRENT_YEAR = 'current_year';
    public const PERIOD_PREVIOUS_YEAR = 'previous_year';
    public const PERIOD_LAST_6_MONTHS = 'last_6_months';
    public const PERIOD_LAST_12_MONTHS = 'last_12_months';
    public const PERIOD_CURRENT_QUARTER = 'current_quarter';
    public const PERIOD_CUSTOM = 'custom';

    private const SESSION_KEY = 'dashboard_period';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CompanyRepository $companyRepository
    ) {
    }

    public static function getPeriodChoices(): array
    {
        return [
            'Année en cours' => self::PERIOD_CURRENT_YEAR,
            'Année précédente' => self::PERIOD_PREVIOUS_YEAR,
            '6 derniers mois' => self::PERIOD_LAST_6_MONTHS,
            '12 derniers mois' => self::PERIOD_LAST_12_MONTHS,
            'Trimestre en cours' => self::PERIOD_CURRENT_QUARTER,
            'Personnalisé' => self::PERIOD_CUSTOM,
        ];
    }

    public function getPeriodDates(
        string $periodType,
        ?\DateTimeImmutable $customStart = null,
        ?\DateTimeImmutable $customEnd = null
    ): array {
        $now = new \DateTimeImmutable();
        $company = $this->companyRepository->findOneBy([]);
        $fiscalYear = $company?->getAnneeFiscaleEnCours() ?? (int) $now->format('Y');

        return match ($periodType) {
            self::PERIOD_CURRENT_YEAR => [
                'start' => new \DateTimeImmutable($fiscalYear . '-01-01 00:00:00'),
                'end' => new \DateTimeImmutable($fiscalYear . '-12-31 23:59:59'),
                'label' => 'Année ' . $fiscalYear,
                'year' => $fiscalYear,
            ],
            self::PERIOD_PREVIOUS_YEAR => [
                'start' => new \DateTimeImmutable(($fiscalYear - 1) . '-01-01 00:00:00'),
                'end' => new \DateTimeImmutable(($fiscalYear - 1) . '-12-31 23:59:59'),
                'label' => 'Année ' . ($fiscalYear - 1),
                'year' => $fiscalYear - 1,
            ],
            self::PERIOD_LAST_6_MONTHS => [
                'start' => $now->modify('-6 months')->modify('first day of this month')->setTime(0, 0, 0),
                'end' => $now->modify('last day of this month')->setTime(23, 59, 59),
                'label' => '6 derniers mois',
                'year' => null,
            ],
            self::PERIOD_LAST_12_MONTHS => [
                'start' => $now->modify('-12 months')->modify('first day of this month')->setTime(0, 0, 0),
                'end' => $now->modify('last day of this month')->setTime(23, 59, 59),
                'label' => '12 derniers mois',
                'year' => null,
            ],
            self::PERIOD_CURRENT_QUARTER => $this->getCurrentQuarterDates($now, $fiscalYear),
            self::PERIOD_CUSTOM => [
                'start' => $customStart ?? $now->modify('first day of this month')->setTime(0, 0, 0),
                'end' => $customEnd ?? $now->modify('last day of this month')->setTime(23, 59, 59),
                'label' => 'Personnalisé',
                'year' => null,
            ],
            default => [
                'start' => new \DateTimeImmutable($fiscalYear . '-01-01 00:00:00'),
                'end' => new \DateTimeImmutable($fiscalYear . '-12-31 23:59:59'),
                'label' => 'Année ' . $fiscalYear,
                'year' => $fiscalYear,
            ],
        };
    }

    private function getCurrentQuarterDates(\DateTimeImmutable $now, int $fiscalYear): array
    {
        $currentMonth = (int) $now->format('m');
        $currentQuarter = (int) ceil($currentMonth / 3);

        $quarterStartMonth = (($currentQuarter - 1) * 3) + 1;
        $quarterEndMonth = $currentQuarter * 3;

        $year = (int) $now->format('Y');

        return [
            'start' => new \DateTimeImmutable(sprintf('%d-%02d-01 00:00:00', $year, $quarterStartMonth)),
            'end' => new \DateTimeImmutable(sprintf('%d-%02d-01 23:59:59', $year, $quarterEndMonth)),
            'label' => 'T' . $currentQuarter . ' ' . $year,
            'year' => null,
        ];
    }

    public function getComparisonPeriodDates(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return [
            'start' => $start->modify('-1 year'),
            'end' => $end->modify('-1 year'),
        ];
    }

    public function savePeriodToSession(string $periodType, ?string $customStart = null, ?string $customEnd = null): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY, [
            'type' => $periodType,
            'customStart' => $periodType === self::PERIOD_CUSTOM ? $customStart : null,
            'customEnd' => $periodType === self::PERIOD_CUSTOM ? $customEnd : null,
        ]);
    }

    public function getPeriodFromSession(): array
    {
        $session = $this->requestStack->getSession();
        $data = $session->get(self::SESSION_KEY, [
            'type' => self::PERIOD_CURRENT_YEAR,
            'customStart' => null,
            'customEnd' => null,
        ]);

        return $data;
    }

    public function getMonthLabelsForPeriod(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $labels = [];
        $current = $start->modify('first day of this month');
        $endMonth = $end->modify('first day of this month');

        $monthNames = [
            1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Aoû',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'
        ];

        while ($current <= $endMonth) {
            $month = (int) $current->format('m');
            $year = $current->format('y');

            // Add year suffix if period spans multiple years
            $startYear = (int) $start->format('Y');
            $endYear = (int) $end->format('Y');

            if ($startYear !== $endYear) {
                $labels[] = $monthNames[$month] . ' ' . $year;
            } else {
                $labels[] = $monthNames[$month];
            }

            $current = $current->modify('+1 month');
        }

        return $labels;
    }

    public function getMonthKeysForPeriod(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $keys = [];
        $current = $start->modify('first day of this month');
        $endMonth = $end->modify('first day of this month');

        while ($current <= $endMonth) {
            $keys[] = $current->format('Y-m');
            $current = $current->modify('+1 month');
        }

        return $keys;
    }
}
