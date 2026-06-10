<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Format;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Models\CompanySettingsRepository;
use Nova\Services\EuerService;
use Nova\Services\PdfService;

final class ReportController extends Controller
{
    public function euer(Request $request): void
    {
        $years = EuerService::years();
        $year  = $request->int('jahr') ?: $years[0];

        $this->view('reports/euer', [
            'title'      => 'EÜR-Auswertung',
            'years'      => $years,
            'year'       => $year,
            'summary'    => EuerService::summary($year),
            'months'     => EuerService::byMonth($year),
            'categories' => EuerService::byCategory($year),
        ]);
    }

    public function euerCsv(Request $request): void
    {
        $year    = $request->int('jahr') ?: (int) date('Y');
        $summary = EuerService::summary($year);
        $months  = EuerService::byMonth($year);
        $cats    = EuerService::byCategory($year);

        $monthNames = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $rows = [];
        $rows[] = ['EÜR ' . $year, '', ''];
        $rows[] = ['Monat', 'Einnahmen', 'Ausgaben'];
        foreach ($months as $m => $v) {
            $rows[] = [$monthNames[$m], $this->de($v['income']), $this->de($v['expense'])];
        }
        $rows[] = ['', '', ''];
        $rows[] = ['Summe Einnahmen', $this->de($summary['income']), ''];
        $rows[] = ['Summe Ausgaben', $this->de($summary['expense']), ''];
        $rows[] = ['Gewinn', $this->de($summary['profit']), ''];
        $rows[] = ['', '', ''];
        $rows[] = ['Ausgaben nach Kategorie', '', ''];
        foreach ($cats['expense'] as $cat => $sum) {
            $rows[] = [$cat, $this->de($sum), ''];
        }

        Response::csv('EUER-' . $year . '.csv', $this->toCsv($rows));
    }

    public function euerPdf(Request $request): void
    {
        $year = $request->int('jahr') ?: (int) date('Y');
        PdfService::stream('pdf/euer', [
            'year'       => $year,
            'summary'    => EuerService::summary($year),
            'months'     => EuerService::byMonth($year),
            'categories' => EuerService::byCategory($year),
            'entries'    => EuerService::entries($year),
            'settings'   => (new CompanySettingsRepository())->get(),
        ], 'EUER-' . $year . '.pdf');
    }

    private function de(int $cents): string
    {
        return Format::amount($cents);
    }

    /** @param array<int,array<int,string>> $rows */
    private function toCsv(array $rows): string
    {
        $out = '';
        foreach ($rows as $row) {
            $escaped = array_map(static function (string $v): string {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row);
            $out .= implode(';', $escaped) . "\r\n";
        }
        return $out;
    }
}
