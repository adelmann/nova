<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\CustomerRepository;
use Nova\Models\RecurringInvoiceRepository;
use Nova\Services\AuditService;
use Nova\Services\LineItemService;

/**
 * Wiederkehrende Rechnungen (Abos/Retainer): Profile verwalten. Die Erzeugung
 * der Rechnungen erledigt der Cron über RecurringService.
 */
final class RecurringController extends Controller
{
    private RecurringInvoiceRepository $repo;

    public function __construct()
    {
        $this->repo = new RecurringInvoiceRepository();
    }

    public function index(Request $request): void
    {
        $this->view('recurring/list', [
            'title'    => 'Wiederkehrende Rechnungen',
            'profiles' => $this->repo->allWithCustomer(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('recurring/form', [
            'title'     => 'Neue wiederkehrende Rechnung',
            'profile'   => $this->emptyProfile(),
            'items'     => [],
            'customers' => (new CustomerRepository())->forSelect(),
            'action'    => '/wiederkehrend',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);
        if ($request->int('customer_id') === 0) {
            Session::flash('error', 'Bitte einen Kunden wählen.');
            $this->redirect('/wiederkehrend/neu');
        }
        [$header, $items] = $this->buildFromRequest($request);
        $id = $this->repo->createWithItems($header, $items);
        AuditService::record('create', 'recurring_invoice', $id, null, ['items' => count($items)]);
        Session::flash('success', 'Wiederkehrende Rechnung angelegt.');
        $this->redirect('/wiederkehrend');
    }

    public function edit(Request $request, array $params): void
    {
        $p = $this->repo->find((int) $params['id']);
        if ($p === null) {
            Response::notFound('Profil nicht gefunden.');
            return;
        }
        $this->view('recurring/form', [
            'title'     => 'Wiederkehrende Rechnung bearbeiten',
            'profile'   => $p,
            'items'     => $this->repo->items((int) $p['id']),
            'customers' => (new CustomerRepository())->forSelect((int) $p['customer_id']),
            'action'    => '/wiederkehrend/' . $p['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id = (int) $params['id'];
        if ($this->repo->find($id) === null) {
            Response::notFound('Profil nicht gefunden.');
            return;
        }
        [$header, $items] = $this->buildFromRequest($request);
        $this->repo->updateWithItems($id, $header, $items);
        AuditService::record('update', 'recurring_invoice', $id, null, ['items' => count($items)]);
        Session::flash('success', 'Wiederkehrende Rechnung aktualisiert.');
        $this->redirect('/wiederkehrend');
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id = (int) $params['id'];
        $p  = $this->repo->find($id);
        if ($p === null) {
            Response::notFound('Profil nicht gefunden.');
            return;
        }
        $this->repo->delete($id);
        AuditService::record('delete', 'recurring_invoice', $id, $p, null);
        Session::flash('success', 'Wiederkehrende Rechnung gelöscht.');
        $this->redirect('/wiederkehrend');
    }

    /**
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>}
     */
    private function buildFromRequest(Request $request): array
    {
        $unit = $request->str('interval_unit', 'month');
        if (!in_array($unit, ['month', 'quarter', 'year'], true)) {
            $unit = 'month';
        }
        $items  = LineItemService::parse($request->post);
        $header = [
            'customer_id'   => $request->int('customer_id'),
            'title'         => $request->str('title'),
            'interval_unit' => $unit,
            'next_date'     => $request->str('next_date') ?: date('Y-m-d'),
            'intro_text'    => $request->str('intro_text'),
            'footer_text'   => $request->str('footer_text'),
            'auto_send'     => $request->bool('auto_send') ? 1 : 0,
            'active'        => $request->bool('active') ? 1 : 0,
        ];
        return [$header, $items];
    }

    /** @return array<string,mixed> */
    private function emptyProfile(): array
    {
        return [
            'id' => null, 'customer_id' => 0, 'title' => '', 'interval_unit' => 'month',
            'next_date' => date('Y-m-d'), 'intro_text' => '', 'footer_text' => '',
            'auto_send' => 0, 'active' => 1,
        ];
    }
}
