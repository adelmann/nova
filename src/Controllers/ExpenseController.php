<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\ExpenseRepository;
use Nova\Models\ReceiptRepository;
use Nova\Services\AuditService;
use Nova\Services\LedgerService;
use Nova\Services\ReceiptService;

final class ExpenseController extends Controller
{
    private ExpenseRepository $repo;

    public function __construct()
    {
        $this->repo = new ExpenseRepository();
    }

    public function index(Request $request): void
    {
        $term = $request->str('q');
        $year = $request->int('jahr') ?: null;
        $expenses = $this->repo->search($term, $year);
        $sum = 0;
        foreach ($expenses as $e) {
            $sum += (int) $e['amount_cents'];
        }
        $this->view('expenses/list', [
            'title'    => 'Ausgaben',
            'expenses' => $expenses,
            'q'        => $term,
            'jahr'     => $year,
            'sum'      => $sum,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('expenses/form', [
            'title'      => 'Neue Ausgabe',
            'expense'    => $this->emptyExpense(),
            'categories' => ExpenseRepository::taxCategories(),
            'receipts'   => [],
            'action'     => '/ausgaben',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);

        $request->post['method'] = pick_value($request->str('method'), $request->str('method_custom'));
        $request->post['supplier'] = pick_value($request->str('supplier'), $request->str('supplier_custom'));
        (new \Nova\Models\VendorRepository())->ensure((string) $request->post['supplier']);
        $id = $this->repo->createFromInput($request->post);
        $this->handleReceiptUpload($request, $id);
        $this->syncLedger($id);
        AuditService::record('create', 'expense', $id, null, $this->repo->find($id));

        Session::flash('success', 'Ausgabe erfasst.');
        $this->redirect('/ausgaben/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $expense = $this->repo->find((int) $params['id']);
        if ($expense === null) {
            Response::notFound('Ausgabe nicht gefunden.');
            return;
        }
        $this->view('expenses/show', [
            'title'    => 'Ausgabe ' . $expense['supplier'],
            'expense'  => $expense,
            'receipts' => (new ReceiptRepository())->forLinkable('expense', (int) $expense['id']),
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $expense = $this->repo->find((int) $params['id']);
        if ($expense === null) {
            Response::notFound('Ausgabe nicht gefunden.');
            return;
        }
        $this->view('expenses/form', [
            'title'      => 'Ausgabe bearbeiten',
            'expense'    => $expense,
            'categories' => ExpenseRepository::taxCategories(),
            'receipts'   => (new ReceiptRepository())->forLinkable('expense', (int) $expense['id']),
            'action'     => '/ausgaben/' . $expense['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id     = (int) $params['id'];
        $before = $this->repo->find($id);
        if ($before === null) {
            Response::notFound('Ausgabe nicht gefunden.');
            return;
        }

        $request->post['method'] = pick_value($request->str('method'), $request->str('method_custom'));
        $request->post['supplier'] = pick_value($request->str('supplier'), $request->str('supplier_custom'));
        (new \Nova\Models\VendorRepository())->ensure((string) $request->post['supplier']);
        $this->repo->updateFromInput($id, $request->post);
        $this->handleReceiptUpload($request, $id);
        $this->syncLedger($id);
        AuditService::record('update', 'expense', $id, $before, $this->repo->find($id));

        Session::flash('success', 'Ausgabe aktualisiert.');
        $this->redirect('/ausgaben/' . $id);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $expense = $this->repo->find($id);
        if ($expense === null) {
            Response::notFound('Ausgabe nicht gefunden.');
            return;
        }

        // Eine bereits ins Journal gebuchte Ausgabe wird nicht hart gelöscht,
        // sondern per Gegenbuchung auf 0 gestellt (GoBD), der Stammsatz bleibt.
        $this->repo->updateFromInput($id, array_merge($expense, ['status' => 'open', 'amount' => '0']));
        LedgerService::syncExpense($id, 0, date('Y-m-d'), 'Storno', 'Storno Ausgabe #' . $id);
        $this->repo->delete($id);
        AuditService::record('delete', 'expense', $id, $expense, null);

        Session::flash('success', 'Ausgabe gelöscht.');
        $this->redirect('/ausgaben');
    }

    private function syncLedger(int $id): void
    {
        $e = $this->repo->find($id);
        if ($e === null) {
            return;
        }
        $target = $e['status'] === 'paid' ? -(int) $e['amount_cents'] : 0;
        LedgerService::syncExpense(
            $id,
            $target,
            $e['expense_date'],
            $e['tax_category'] ?: ($e['category'] ?: 'Sonstiges'),
            trim(($e['supplier'] ?: 'Ausgabe') . ' – ' . ($e['note'] ?: $e['category']))
        );
    }

    private function handleReceiptUpload(Request $request, int $expenseId): void
    {
        $files = ReceiptService::normalizeUploads($request->files['receipt'] ?? null);
        if ($files === []) {
            return;
        }
        $repo = new ReceiptRepository();
        $type = $request->str('receipt_type', 'eingangsrechnung');
        $saved = 0;
        foreach ($files as $file) {
            try {
                $meta = ReceiptService::storeReceipt($file);
                $repo->createFromUpload($meta, $type, 'expense', $expenseId);
                $saved++;
            } catch (\RuntimeException $e) {
                Session::flash('warn', 'Ein Beleg konnte nicht gespeichert werden: ' . $e->getMessage());
            }
        }
        if ($saved > 1) {
            Session::flash('success', $saved . ' Belege angehängt.');
        }
    }

    /** @return array<string,mixed> */
    private function emptyExpense(): array
    {
        return [
            'id' => null, 'expense_date' => date('Y-m-d'), 'supplier' => '', 'category' => '',
            'tax_category' => '', 'amount_cents' => 0, 'vat_rate' => 0, 'method' => '',
            'status' => 'paid', 'note' => '',
        ];
    }
}
