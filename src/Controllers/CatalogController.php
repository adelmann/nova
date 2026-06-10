<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\CatalogItemRepository;
use Nova\Services\AuditService;

/**
 * Leistungskatalog: wiederverwendbare Positionen für Angebote/Rechnungen.
 */
final class CatalogController extends Controller
{
    private CatalogItemRepository $repo;

    public function __construct()
    {
        $this->repo = new CatalogItemRepository();
    }

    public function index(Request $request): void
    {
        $this->view('catalog/list', [
            'title' => 'Leistungskatalog',
            'items' => $this->repo->all(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('catalog/form', [
            'title'  => 'Neue Katalog-Position',
            'item'   => ['id' => null, 'name' => '', 'unit' => 'Std', 'unit_price_cents' => 0, 'archived_at' => null],
            'action' => '/katalog',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);
        if ($request->str('name') === '') {
            Session::flash('error', 'Bitte eine Bezeichnung angeben.');
            $this->redirect('/katalog/neu');
        }
        $id = $this->repo->createFromInput($request->post);
        AuditService::record('create', 'catalog_item', $id, null, $this->repo->find($id));
        Session::flash('success', 'Katalog-Position angelegt.');
        $this->redirect('/katalog');
    }

    public function edit(Request $request, array $params): void
    {
        $item = $this->repo->find((int) $params['id']);
        if ($item === null) {
            Response::notFound('Position nicht gefunden.');
            return;
        }
        $this->view('catalog/form', [
            'title'  => 'Katalog-Position bearbeiten',
            'item'   => $item,
            'action' => '/katalog/' . $item['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id     = (int) $params['id'];
        $before = $this->repo->find($id);
        if ($before === null) {
            Response::notFound('Position nicht gefunden.');
            return;
        }
        $this->repo->updateFromInput($id, $request->post);
        AuditService::record('update', 'catalog_item', $id, $before, $this->repo->find($id));
        Session::flash('success', 'Katalog-Position aktualisiert.');
        $this->redirect('/katalog');
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id   = (int) $params['id'];
        $item = $this->repo->find($id);
        if ($item === null) {
            Response::notFound('Position nicht gefunden.');
            return;
        }
        $this->repo->delete($id);
        AuditService::record('delete', 'catalog_item', $id, $item, null);
        Session::flash('success', 'Katalog-Position gelöscht.');
        $this->redirect('/katalog');
    }
}
