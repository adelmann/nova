<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Models\LedgerRepository;

final class LedgerController extends Controller
{
    public function index(Request $request): void
    {
        $repo  = new LedgerRepository();
        $years = $repo->years();
        $year  = $request->int('jahr') ?: $years[0];

        $entries = $repo->forYear($year);
        $income  = 0;
        $expense = 0;
        foreach ($entries as $e) {
            if ($e['type'] === 'income') {
                $income += (int) $e['amount_cents'];
            } else {
                $expense += (int) $e['amount_cents'];
            }
        }

        $this->view('ledger/index', [
            'title'   => 'Buchungsjournal',
            'entries' => $entries,
            'years'   => $years,
            'year'    => $year,
            'income'  => $income,
            'expense' => $expense,
        ]);
    }
}
