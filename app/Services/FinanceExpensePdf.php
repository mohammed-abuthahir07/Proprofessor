<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use SimplePdf;

final class FinanceExpensePdf
{
    /**
     * @param array<string,mixed> $institution
     * @param array<string,mixed> $user
     */
    public static function send(array $institution, array $user, string $scope, int $year, int $month): void
    {
        $built = self::build($institution, $user, $scope, $year, $month);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $built['filename'] . '"');
        header('Content-Length: ' . strlen($built['bytes']));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $built['bytes'];
        exit;
    }

    /**
     * @param array<string,mixed> $institution
     * @param array<string,mixed> $user
     * @return array{bytes:string,filename:string}
     */
    public static function build(array $institution, array $user, string $scope, int $year, int $month): array
    {
        if (!class_exists(SimplePdf::class, false)) {
            require_once dirname(__DIR__, 2) . '/includes/SimplePdf.php';
        }

        $scope = $scope === 'year' ? 'year' : 'month';
        $instId = (int)($institution['id'] ?? 0);
        $month = max(1, min(12, $month));
        $monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $monthShort = date('M', mktime(0, 0, 0, $month, 1, $year));

        $expenses = Expense::listForStatement($instId, $year, $scope === 'month' ? $month : null);
        $categories = Expense::totalsByCategoryForPeriod($instId, $year, $scope === 'month' ? $month : null);
        $top = Expense::topCategoryForPeriod($instId, $year, $scope === 'month' ? $month : null);
        $total = $scope === 'month'
            ? Expense::totalForMonth($instId, $year, $month)
            : Expense::totalForYear($instId, $year);
        $byMonth = $scope === 'year' ? Expense::totalsByMonthForYear($instId, $year) : [];

        $settings = json_decode((string)($institution['settings'] ?? '{}'), true);
        if (!is_array($settings)) {
            $settings = [];
        }
        $navy = self::rgb((string)($settings['brand_secondary'] ?? $settings['secondary_color'] ?? '0F172A'));
        $accent = self::rgb((string)($settings['brand_accent'] ?? $settings['accent_color'] ?? 'D97706'));
        $ink = [20, 24, 40];
        $muted = [90, 98, 120];

        $college = trim((string)($institution['name'] ?? 'Institution'));
        $code = trim((string)($institution['code'] ?? ''));
        $addrParts = array_filter([
            trim((string)($institution['address'] ?? '')),
            trim((string)($institution['city'] ?? '')),
            trim((string)($institution['state'] ?? '')),
            trim((string)($institution['pincode'] ?? '')),
        ], static fn($v) => $v !== '');
        $addressLine = implode(', ', $addrParts);
        $affiliation = trim((string)($institution['affiliation_university'] ?? ''));
        $naac = trim((string)($institution['naac_grade'] ?? ''));
        $phone = trim((string)($institution['phone'] ?? ''));
        $email = trim((string)($institution['email'] ?? ''));
        $preparedBy = trim((string)($user['full_name'] ?? 'College Admin'));

        $pdf = new SimplePdf();
        $pdf->filledRect(0, 0, $pdf->pageWidth(), 38, $navy);
        $pdf->filledRect(0, 38, $pdf->pageWidth(), 3.2, $accent);
        $pdf->setFont(9, true);
        $pdf->textAt(42, 13, $code !== '' ? strtoupper($code) . '  ·  ACCOUNTS OFFICE' : 'ACCOUNTS OFFICE', [255, 255, 255]);
        $pdf->setFont(8, false);
        $pdf->textAt(390, 14, date('d M Y'), [203, 213, 225]);
        $pdf->moveTo(56);

        $pdf->setFont(16, true);
        $pdf->writeCenteredWrapped($college !== '' ? $college : 'Institution', 0, 20, $ink);
        $pdf->setFont(9, false);
        if ($addressLine !== '') {
            $pdf->writeCenteredWrapped($addressLine, 0, 12, $muted);
        }
        if ($affiliation !== '') {
            $pdf->writeCentered('Affiliated to ' . $affiliation, $muted, 12);
        }
        $metaBits = array_filter([
            $naac !== '' ? ('NAAC ' . $naac) : '',
            $phone !== '' ? $phone : '',
            $email !== '' ? $email : '',
        ], static fn($v) => $v !== '');
        if ($metaBits) {
            $pdf->writeCentered(implode('  ·  ', $metaBits), $muted, 12);
        }
        $pdf->space(4);
        $pdf->doubleRule($navy);

        $pdf->setFont(13, true);
        if ($scope === 'month') {
            $pdf->writeCentered('MONTHLY EXPENSE STATEMENT', $ink, 18);
            $pdf->setFont(11, true);
            $pdf->writeCentered($monthLabel, $accent, 15);
        } else {
            $pdf->writeCentered('ANNUAL EXPENSE STATEMENT', $ink, 18);
            $pdf->setFont(11, true);
            $pdf->writeCentered('Financial Year ' . $year, $accent, 15);
        }
        $pdf->setFont(9, false);
        $pdf->writeCentered('Prepared by ' . $preparedBy . '  ·  College Admin', $muted, 12);
        $pdf->space(4);
        $pdf->thinRule($accent);

        $pdf->setFont(12, true);
        $pdf->writeLine('1. Period snapshot', $ink, 18);
        $pdf->setFont(9, false);
        $snapshot = [
            ['College', $college !== '' ? $college : '—'],
            ['Period', $scope === 'month' ? $monthLabel : ('January – December ' . $year)],
            ['Entries recorded', (string)count($expenses)],
            ['Total expenditure', self::money($total)],
            ['Highest category', $top ? ((string)$top['category'] . '  (' . self::money((float)$top['total']) . ')') : '—'],
        ];
        $pdf->table(['Particulars', 'Details'], $snapshot, [2.2, 3.6], 9.5);
        $pdf->space(10);

        if ($scope === 'year') {
            $pdf->setFont(12, true);
            $pdf->writeLine('2. Month-wise expenditure', $ink, 18);
            $pdf->setFont(9, false);
            $pdf->writeWrapped(
                'All twelve months of ' . $year . ' are listed. Months with no recorded expense show Rs. 0.00.',
                0,
                12,
                $muted
            );
            $pdf->space(4);
            $monthRows = [];
            $monthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
            foreach ($monthNames as $num => $label) {
                $row = $byMonth[$num] ?? ['total' => 0.0, 'entries' => 0];
                $monthRows[] = [
                    (string)$num,
                    $label,
                    (string)(int)$row['entries'],
                    self::money((float)$row['total']),
                ];
            }
            $monthRows[] = ['', 'Total ' . $year, (string)count($expenses), self::money($total)];
            $pdf->table(['#', 'Month', 'Entries', 'Amount'], $monthRows, [0.5, 2.4, 1.1, 1.8], 9);
            $pdf->space(10);
        }

        $section = $scope === 'year' ? '3' : '2';
        $pdf->setFont(12, true);
        $pdf->writeLine($section . '. Category-wise expenditure', $ink, 18);
        $pdf->setFont(9, false);
        $catRows = [];
        foreach ($categories as $i => $cat) {
            $catRows[] = [
                (string)($i + 1),
                (string)($cat['category'] ?? ''),
                self::money((float)($cat['total'] ?? 0)),
            ];
        }
        if (!$catRows) {
            $catRows[] = ['—', 'No expenses in this period', 'Rs. 0.00'];
        }
        $catRows[] = ['', 'Total', self::money($total)];
        $pdf->table(['#', 'Category', 'Amount'], $catRows, [0.5, 3.2, 1.8], 9);
        $pdf->space(10);

        $section = $scope === 'year' ? '4' : '3';
        $pdf->setFont(12, true);
        $pdf->writeLine($section . '. Expense register', $ink, 18);
        $pdf->setFont(9, false);
        $pdf->writeWrapped(
            $scope === 'month'
                ? ('Every expense recorded in ' . $monthLabel . ' is listed below.')
                : ('Every expense recorded in ' . $year . ' is listed below, across all months.'),
            0,
            12,
            $muted
        );
        $pdf->space(4);
        $ledgerRows = [];
        foreach ($expenses as $i => $row) {
            $dept = trim((string)($row['dept_name'] ?? ''));
            $ledgerRows[] = [
                (string)($i + 1),
                self::niceDate((string)($row['expense_date'] ?? '')),
                trim((string)($row['title'] ?? '')),
                $dept !== '' ? $dept : 'Institution',
                (string)($row['category'] ?? ''),
                self::money((float)($row['amount'] ?? 0)),
            ];
        }
        if (!$ledgerRows) {
            $ledgerRows[] = ['—', '—', 'No expenses recorded', '—', '—', 'Rs. 0.00'];
        }
        $pdf->table(
            ['#', 'Date', 'Title', 'Department', 'Category', 'Amount'],
            $ledgerRows,
            [0.4, 1.15, 2.15, 1.45, 1.2, 1.15],
            8
        );
        $pdf->space(12);

        $pdf->thinRule($muted);
        $pdf->setFont(8, false);
        $pdf->writeWrapped(
            'This statement is generated from ProProfessor AI finance records for '
            . ($college !== '' ? $college : 'the institution')
            . '. Figures are in Indian Rupees. Use as supporting accounts documentation for internal audit and statutory review.',
            0,
            11,
            $muted
        );
        $pdf->space(8);
        $pdf->writeTwoColumn('Prepared by: ' . $preparedBy, 'Confidential · Internal use', $muted, 12);
        $pdf->writeTwoColumn('Office of the College Admin', 'Generated ' . date('d M Y, h:i A'), $muted, 12);

        $pdf->stampPageNumbers();
        $bytes = $pdf->output();
        $safeCollege = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $college) ?: 'College';
        $filename = $scope === 'month'
            ? 'Expense_' . $monthShort . '_' . $year . '_' . $safeCollege . '.pdf'
            : 'Expense_Year_' . $year . '_' . $safeCollege . '.pdf';

        return ['bytes' => $bytes, 'filename' => $filename];
    }

    private static function money(float $amount): string
    {
        return 'Rs. ' . number_format($amount, 2);
    }

    private static function niceDate(string $ymd): string
    {
        $ts = strtotime($ymd);
        return $ts ? date('d M Y', $ts) : '—';
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        $hex = strtoupper(ltrim($hex, '#'));
        if (!preg_match('/^[0-9A-F]{6}$/', $hex)) {
            return [15, 23, 42];
        }
        return [
            (int)hexdec(substr($hex, 0, 2)),
            (int)hexdec(substr($hex, 2, 2)),
            (int)hexdec(substr($hex, 4, 2)),
        ];
    }
}
