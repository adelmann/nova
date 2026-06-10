<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Format;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\CustomerRepository;
use Nova\Models\InvoiceRepository;
use Nova\Models\ProjectItemRepository;
use Nova\Models\ProjectRepository;
use Nova\Models\QuoteRepository;
use Nova\Services\AuditService;
use Nova\Services\LineItemService;

final class ProjectController extends Controller
{
    private ProjectRepository $repo;
    private ProjectItemRepository $items;

    public function __construct()
    {
        $this->repo  = new ProjectRepository();
        $this->items = new ProjectItemRepository();
    }

    public function index(Request $request): void
    {
        $this->view('projects/list', [
            'title'    => 'Projekte',
            'projects' => $this->repo->allWithCustomer(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('projects/form', [
            'title'     => 'Neues Projekt',
            'project'   => $this->emptyProject((int) $request->int('customer_id')),
            'customers' => (new CustomerRepository())->forSelect(),
            'action'    => '/projekte',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);

        $isInternal = $request->str('project_type') === 'internal';
        if ($request->str('name') === '') {
            Session::flash('error', 'Bitte einen Projektnamen angeben.');
            $this->redirect('/projekte/neu');
        }
        if (!$isInternal && $request->int('customer_id') === 0) {
            Session::flash('error', 'Bitte einen Kunden wählen oder das Projekt als intern markieren.');
            $this->redirect('/projekte/neu');
        }

        $id = $this->repo->createFromInput($request->post);
        AuditService::record('create', 'project', $id, null, $this->repo->find($id));

        Session::flash('success', 'Projekt angelegt.');
        $this->redirect('/projekte/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $project = $this->repo->findWithCustomer((int) $params['id']);
        if ($project === null) {
            Response::notFound('Projekt nicht gefunden.');
            return;
        }
        $this->view('projects/show', [
            'title'         => $project['name'],
            'project'       => $project,
            'items'         => $this->items->forProject((int) $project['id']),
            'unbilledCents' => $this->items->unbilledTotalCents((int) $project['id']),
        ]);
    }

    /** Eine abrechenbare Leistung zum Projekt erfassen. */
    public function addItem(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $project = $this->repo->find($id);
        if ($project === null) {
            Response::notFound('Projekt nicht gefunden.');
            return;
        }
        if ($request->str('description') === '') {
            Session::flash('error', 'Bitte eine Beschreibung der Leistung angeben.');
            $this->redirect('/projekte/' . $id);
        }

        // Einzelpreis: Eingabe oder – falls leer – Projekt-Stundensatz.
        $priceInput = $request->str('unit_price');
        $price      = $priceInput !== '' ? Format::toCents($priceInput) : (int) $project['hourly_rate_cents'];

        $itemId = $this->items->add([
            'project_id'       => $id,
            'item_date'        => $request->str('item_date'),
            'description'      => $request->str('description'),
            'quantity'         => $this->parseQty($request->str('quantity', '1')),
            'unit'             => $request->str('unit', 'Std'),
            'unit_price_cents' => $price,
        ]);
        AuditService::record('create', 'project_item', $itemId, null, ['project_id' => $id]);

        Session::flash('success', 'Leistung erfasst.');
        $this->redirect('/projekte/' . $id);
    }

    /** Eine noch nicht abgerechnete Leistung löschen. */
    public function deleteItem(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id     = (int) $params['id'];
        $itemId = (int) $params['itemId'];
        $item   = $this->items->find($itemId);
        if ($item === null || (int) $item['project_id'] !== $id) {
            Response::notFound('Leistung nicht gefunden.');
            return;
        }
        if (!empty($item['billed_doc_id'])) {
            Session::flash('error', 'Bereits abgerechnete Leistungen können nicht gelöscht werden.');
            $this->redirect('/projekte/' . $id);
        }

        $this->items->delete($itemId);
        AuditService::record('delete', 'project_item', $itemId, $item, null);
        Session::flash('success', 'Leistung gelöscht.');
        $this->redirect('/projekte/' . $id);
    }

    /** Erzeugt einen Angebots-Entwurf aus den offenen Leistungen des Projekts. */
    public function createQuote(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $this->billFromProject($request, (int) $params['id'], 'quote');
    }

    /** Erzeugt einen Rechnungs-Entwurf aus den offenen Leistungen des Projekts. */
    public function createInvoice(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $this->billFromProject($request, (int) $params['id'], 'invoice');
    }

    /**
     * Gemeinsame Logik: offene Leistungen in einen Angebots-/Rechnungsentwurf
     * übernehmen und als abgerechnet markieren.
     */
    private function billFromProject(Request $request, int $id, string $docType): void
    {
        $project = $this->repo->find($id);
        if ($project === null) {
            Response::notFound('Projekt nicht gefunden.');
            return;
        }
        if (empty($project['customer_id'])) {
            Session::flash('error', 'Interne Projekte ohne Kunden können nicht abgerechnet werden.');
            $this->redirect('/projekte/' . $id);
        }

        $open = $this->items->unbilled($id);
        if ($open === []) {
            Session::flash('error', 'Keine offenen Leistungen zum Abrechnen vorhanden.');
            $this->redirect('/projekte/' . $id);
        }

        $settings = (new CompanySettingsRepository())->get();
        $isKU     = (int) $settings['is_kleinunternehmer'] === 1;
        $vatRate  = $isKU ? 0 : (int) $settings['default_vat_rate'];

        $pos   = 0;
        $items = [];
        foreach ($open as $row) {
            $qty   = (float) $row['quantity'];
            $price = (int) $row['unit_price_cents'];
            $items[] = [
                'position'         => ++$pos,
                'description'      => (string) $row['description'],
                'quantity'         => $qty,
                'unit'             => (string) $row['unit'],
                'unit_price_cents' => $price,
                'vat_rate'         => $vatRate,
                'line_total_cents' => (int) round($qty * $price),
            ];
        }
        $totals = LineItemService::totals($items, $vatRate, $isKU);

        if ($docType === 'quote') {
            $header = [
                'customer_id' => (int) $project['customer_id'], 'project_id' => $id, 'status' => 'draft',
                'quote_date' => date('Y-m-d'), 'is_kleinunternehmer' => $isKU ? 1 : 0, 'vat_rate' => $vatRate,
                'intro_text' => '', 'footer_text' => (string) $settings['quote_footer_text'],
                'net_total_cents' => $totals['net_total_cents'], 'vat_total_cents' => $totals['vat_total_cents'],
                'gross_total_cents' => $totals['gross_total_cents'],
            ];
            $docId = (new QuoteRepository())->createWithItems($header, $items);
            $this->items->markBilled(array_column($open, 'id'), 'quote', $docId);
            AuditService::record('create', 'quote', $docId, ['from_project' => $id], ['items' => count($items)]);
            Session::flash('success', count($items) . ' Leistung(en) in Angebotsentwurf übernommen.');
            $this->redirect('/angebote/' . $docId);
        }

        $header = [
            'customer_id' => (int) $project['customer_id'], 'project_id' => $id, 'status' => 'draft', 'is_locked' => 0,
            'invoice_date' => date('Y-m-d'), 'is_kleinunternehmer' => $isKU ? 1 : 0, 'vat_rate' => $vatRate,
            'intro_text' => '', 'footer_text' => (string) $settings['invoice_footer_text'],
            'net_total_cents' => $totals['net_total_cents'], 'vat_total_cents' => $totals['vat_total_cents'],
            'gross_total_cents' => $totals['gross_total_cents'],
        ];
        $docId = (new InvoiceRepository())->createWithItems($header, $items);
        $this->items->markBilled(array_column($open, 'id'), 'invoice', $docId);
        AuditService::record('create', 'invoice', $docId, ['from_project' => $id], ['items' => count($items)]);
        Session::flash('success', count($items) . ' Leistung(en) in Rechnungsentwurf übernommen.');
        $this->redirect('/rechnungen/' . $docId);
    }

    private function parseQty(string $value): float
    {
        $value = str_replace([' '], '', trim($value));
        $value = str_replace(',', '.', $value);
        return $value === '' ? 1.0 : (float) $value;
    }

    public function edit(Request $request, array $params): void
    {
        $project = $this->repo->find((int) $params['id']);
        if ($project === null) {
            Response::notFound('Projekt nicht gefunden.');
            return;
        }
        $this->view('projects/form', [
            'title'     => 'Projekt bearbeiten',
            'project'   => $project,
            'customers' => (new CustomerRepository())->forSelect((int) ($project['customer_id'] ?? 0)),
            'action'    => '/projekte/' . $project['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id     = (int) $params['id'];
        $before = $this->repo->find($id);
        if ($before === null) {
            Response::notFound('Projekt nicht gefunden.');
            return;
        }

        $this->repo->updateFromInput($id, $request->post);
        AuditService::record('update', 'project', $id, $before, $this->repo->find($id));

        Session::flash('success', 'Projekt aktualisiert.');
        $this->redirect('/projekte/' . $id);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $project = $this->repo->find($id);
        if ($project === null) {
            Response::notFound('Projekt nicht gefunden.');
            return;
        }

        $this->repo->delete($id);
        AuditService::record('delete', 'project', $id, $project, null);

        Session::flash('success', 'Projekt gelöscht.');
        $this->redirect('/projekte');
    }

    /** @return array<string,mixed> */
    private function emptyProject(int $customerId): array
    {
        return [
            'id' => null, 'customer_id' => $customerId, 'project_type' => $customerId > 0 ? 'customer' : 'customer',
            'name' => '', 'status' => 'active',
            'hourly_rate_cents' => 0, 'description' => '', 'start_date' => '', 'end_date' => '',
        ];
    }
}
