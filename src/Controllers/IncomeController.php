<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\IncomeRepository;
use Nova\Models\ProjectRepository;
use Nova\Services\AuditService;
use Nova\Services\LedgerService;

final class IncomeController extends Controller
{
    private IncomeRepository $repo;

    public function __construct()
    {
        $this->repo = new IncomeRepository();
    }

    public function index(Request $request): void
    {
        $term = $request->str('q');
        $year = $request->int('jahr') ?: null;
        $incomes = $this->repo->search($term, $year);
        $sum = 0;
        foreach ($incomes as $i) {
            $sum += (int) $i['amount_cents'];
        }
        $this->view('income/list', [
            'title'   => 'Einnahmen',
            'incomes' => $incomes,
            'q'       => $term,
            'jahr'    => $year,
            'sum'     => $sum,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('income/form', [
            'title'      => 'Neue Einnahme',
            'income'     => $this->emptyIncome(),
            'categories' => IncomeRepository::categories(),
            'projects'   => (new ProjectRepository())->allWithCustomer(),
            'action'     => '/einnahmen',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);

        $id = $this->repo->createFromInput($request->post);
        $this->syncLedger($id);
        AuditService::record('create', 'income', $id, null, $this->repo->find($id));

        Session::flash('success', 'Einnahme erfasst.');
        $this->redirect('/einnahmen/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $income = $this->repo->findWithProject((int) $params['id']);
        if ($income === null) {
            Response::notFound('Einnahme nicht gefunden.');
            return;
        }
        $this->view('income/show', ['title' => 'Einnahme', 'income' => $income]);
    }

    public function edit(Request $request, array $params): void
    {
        $income = $this->repo->find((int) $params['id']);
        if ($income === null) {
            Response::notFound('Einnahme nicht gefunden.');
            return;
        }
        $this->view('income/form', [
            'title'      => 'Einnahme bearbeiten',
            'income'     => $income,
            'categories' => IncomeRepository::categories(),
            'projects'   => (new ProjectRepository())->allWithCustomer(),
            'action'     => '/einnahmen/' . $income['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id     = (int) $params['id'];
        $before = $this->repo->find($id);
        if ($before === null) {
            Response::notFound('Einnahme nicht gefunden.');
            return;
        }

        $this->repo->updateFromInput($id, $request->post);
        $this->syncLedger($id);
        AuditService::record('update', 'income', $id, $before, $this->repo->find($id));

        Session::flash('success', 'Einnahme aktualisiert.');
        $this->redirect('/einnahmen/' . $id);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id     = (int) $params['id'];
        $income = $this->repo->find($id);
        if ($income === null) {
            Response::notFound('Einnahme nicht gefunden.');
            return;
        }

        // Per Gegenbuchung auf 0 stellen (Journal bleibt unveränderbar), dann löschen.
        LedgerService::syncIncome($id, 0, date('Y-m-d'), 'Storno', 'Storno Einnahme #' . $id);
        $this->repo->delete($id);
        AuditService::record('delete', 'income', $id, $income, null);

        Session::flash('success', 'Einnahme gelöscht.');
        $this->redirect('/einnahmen');
    }

    private function syncLedger(int $id): void
    {
        $i = $this->repo->find($id);
        if ($i === null) {
            return;
        }
        LedgerService::syncIncome(
            $id,
            (int) $i['amount_cents'],
            $i['income_date'],
            $i['category'] ?: 'Sonstige Einnahmen',
            trim(($i['source'] ?: 'Einnahme') . ($i['note'] ? ' – ' . $i['note'] : ''))
        );
    }

    /** @return array<string,mixed> */
    private function emptyIncome(): array
    {
        return [
            'id' => null, 'income_date' => date('Y-m-d'), 'source' => '', 'category' => 'Affiliate',
            'project_id' => null, 'amount_cents' => 0, 'note' => '',
        ];
    }
}
