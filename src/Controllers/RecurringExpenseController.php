<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\ExpenseRepository;
use Nova\Models\RecurringExpenseRepository;
use Nova\Models\VendorRepository;
use Nova\Services\AuditService;

/**
 * Wiederkehrende Ausgaben (Daueraufwendungen): Profile verwalten. Die Erzeugung
 * der Ausgaben erledigt der Cron über RecurringExpenseService.
 */
final class RecurringExpenseController extends Controller
{
    private RecurringExpenseRepository $repo;

    public function __construct()
    {
        $this->repo = new RecurringExpenseRepository();
    }

    public function index(Request $request): void
    {
        $this->view('recurring_expenses/list', [
            'title'    => 'Dauerausgaben',
            'profiles' => $this->repo->allOrdered(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('recurring_expenses/form', [
            'title'      => 'Neue Dauerausgabe',
            'profile'    => $this->emptyProfile(),
            'categories' => ExpenseRepository::taxCategories(),
            'action'     => '/dauerausgaben',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);
        $this->prepare($request);
        $id = $this->repo->createFromInput($request->post);
        AuditService::record('create', 'recurring_expense', $id, null, $this->repo->find($id));
        Session::flash('success', 'Dauerausgabe angelegt.');
        $this->redirect('/dauerausgaben');
    }

    public function edit(Request $request, array $params): void
    {
        $profile = $this->repo->find((int) $params['id']);
        if ($profile === null) {
            Response::notFound('Dauerausgabe nicht gefunden.');
            return;
        }
        $this->view('recurring_expenses/form', [
            'title'      => 'Dauerausgabe bearbeiten',
            'profile'    => $profile,
            'categories' => ExpenseRepository::taxCategories(),
            'action'     => '/dauerausgaben/' . $profile['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id     = (int) $params['id'];
        $before = $this->repo->find($id);
        if ($before === null) {
            Response::notFound('Dauerausgabe nicht gefunden.');
            return;
        }
        $this->prepare($request);
        $this->repo->updateFromInput($id, $request->post);
        AuditService::record('update', 'recurring_expense', $id, $before, $this->repo->find($id));
        Session::flash('success', 'Dauerausgabe aktualisiert.');
        $this->redirect('/dauerausgaben');
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id      = (int) $params['id'];
        $profile = $this->repo->find($id);
        if ($profile === null) {
            Response::notFound('Dauerausgabe nicht gefunden.');
            return;
        }
        $this->repo->delete($id);
        AuditService::record('delete', 'recurring_expense', $id, $profile, null);
        Session::flash('success', 'Dauerausgabe gelöscht. Bereits gebuchte Ausgaben bleiben erhalten.');
        $this->redirect('/dauerausgaben');
    }

    /** Lieferant/Zahlungsart aus Select+Freitext zusammenführen und Vendor anlegen. */
    private function prepare(Request $request): void
    {
        $request->post['method']   = pick_value($request->str('method'), $request->str('method_custom'));
        $request->post['supplier'] = pick_value($request->str('supplier'), $request->str('supplier_custom'));
        if (trim((string) $request->post['supplier']) !== '') {
            (new VendorRepository())->ensure((string) $request->post['supplier']);
        }
    }

    /** @return array<string,mixed> */
    private function emptyProfile(): array
    {
        return [
            'id' => null, 'title' => '', 'supplier' => '', 'category' => '', 'tax_category' => '',
            'amount_cents' => 0, 'vat_rate' => 0, 'method' => '', 'interval_unit' => 'month',
            'next_date' => date('Y-m-d'), 'note' => '', 'active' => 1,
        ];
    }
}
