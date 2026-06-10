<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\VendorRepository;
use Nova\Services\AuditService;

/**
 * Stammdaten für Lieferanten & Dienstleister (Zahlungsempfänger von Ausgaben).
 */
final class VendorController extends Controller
{
    private VendorRepository $repo;

    public function __construct()
    {
        $this->repo = new VendorRepository();
    }

    public function index(Request $request): void
    {
        $term         = $request->str('q');
        $showArchived = $request->bool('archiv');
        $this->view('vendors/list', [
            'title'        => 'Lieferanten & Dienstleister',
            'vendors'      => $this->repo->search($term, $showArchived),
            'q'            => $term,
            'showArchived' => $showArchived,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('vendors/form', [
            'title'  => 'Neuer Lieferant',
            'vendor' => $this->emptyVendor(),
            'action' => '/lieferanten',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);
        if ($request->str('name') === '') {
            Session::flash('error', 'Bitte einen Namen angeben.');
            $this->redirect('/lieferanten/neu');
        }
        $id = $this->repo->createFromInput($request->post);
        AuditService::record('create', 'vendor', $id, null, $this->repo->find($id));
        Session::flash('success', 'Lieferant angelegt.');
        $this->redirect('/lieferanten/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $vendor = $this->repo->find((int) $params['id']);
        if ($vendor === null) {
            Response::notFound('Lieferant nicht gefunden.');
            return;
        }
        $this->view('vendors/show', ['title' => $vendor['name'], 'vendor' => $vendor]);
    }

    public function edit(Request $request, array $params): void
    {
        $vendor = $this->repo->find((int) $params['id']);
        if ($vendor === null) {
            Response::notFound('Lieferant nicht gefunden.');
            return;
        }
        $this->view('vendors/form', [
            'title'  => 'Lieferant bearbeiten',
            'vendor' => $vendor,
            'action' => '/lieferanten/' . $vendor['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id     = (int) $params['id'];
        $before = $this->repo->find($id);
        if ($before === null) {
            Response::notFound('Lieferant nicht gefunden.');
            return;
        }
        if ($request->str('name') === '') {
            Session::flash('error', 'Bitte einen Namen angeben.');
            $this->redirect('/lieferanten/' . $id . '/bearbeiten');
        }
        $this->repo->updateFromInput($id, $request->post);
        AuditService::record('update', 'vendor', $id, $before, $this->repo->find($id));
        Session::flash('success', 'Lieferant aktualisiert.');
        $this->redirect('/lieferanten/' . $id);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id     = (int) $params['id'];
        $vendor = $this->repo->find($id);
        if ($vendor === null) {
            Response::notFound('Lieferant nicht gefunden.');
            return;
        }
        $this->repo->delete($id);
        AuditService::record('delete', 'vendor', $id, $vendor, null);
        Session::flash('success', 'Lieferant gelöscht.');
        $this->redirect('/lieferanten');
    }

    public function archive(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id = (int) $params['id'];
        if ($this->repo->find($id) === null) {
            Response::notFound('Lieferant nicht gefunden.');
            return;
        }
        $this->repo->archive($id);
        AuditService::record('update', 'vendor', $id, null, ['archived' => true]);
        Session::flash('success', 'Lieferant archiviert.');
        $this->redirect('/lieferanten/' . $id);
    }

    public function unarchive(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id = (int) $params['id'];
        if ($this->repo->find($id) === null) {
            Response::notFound('Lieferant nicht gefunden.');
            return;
        }
        $this->repo->unarchive($id);
        AuditService::record('update', 'vendor', $id, null, ['archived' => false]);
        Session::flash('success', 'Lieferant wiederhergestellt.');
        $this->redirect('/lieferanten/' . $id);
    }

    /** @return array<string,mixed> */
    private function emptyVendor(): array
    {
        return [
            'id' => null, 'name' => '', 'contact_name' => '', 'email' => '', 'phone' => '',
            'website' => '', 'address_line1' => '', 'zip' => '', 'city' => '', 'vat_id' => '',
            'note' => '', 'archived_at' => null,
        ];
    }
}
