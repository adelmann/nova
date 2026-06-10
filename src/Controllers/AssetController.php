<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\AssetRepository;
use Nova\Models\VendorRepository;
use Nova\Services\AuditService;
use Nova\Services\DepreciationService;

/**
 * Anlagevermögen & AfA. Anlagegüter werden erfasst; die jährliche Abschreibung
 * bucht der Cron (bzw. beim Anlegen für bereits abgeschlossene Vorjahre) über
 * den DepreciationService ins Journal/EÜR.
 */
final class AssetController extends Controller
{
    private AssetRepository $repo;

    public function __construct()
    {
        $this->repo = new AssetRepository();
    }

    public function index(Request $request): void
    {
        $year   = (int) date('Y');
        $assets = $this->repo->allOrdered();
        $rows   = [];
        $totalCost = 0;
        $totalBook = 0;
        foreach ($assets as $a) {
            $book = DepreciationService::bookValue($a, $year);
            $totalCost += (int) $a['cost_cents'];
            $totalBook += $book;
            $a['book_value'] = $book;
            $rows[] = $a;
        }
        $this->view('assets/list', [
            'title'     => 'Anlagevermögen',
            'assets'    => $rows,
            'year'      => $year,
            'totalCost' => $totalCost,
            'totalBook' => $totalBook,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('assets/form', [
            'title'  => 'Neues Anlagegut',
            'asset'  => $this->emptyAsset(),
            'lifes'  => AssetRepository::usefulLifeSuggestions(),
            'action' => '/anlagen',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);
        $request->post['supplier'] = pick_value($request->str('supplier'), $request->str('supplier_custom'));
        if (trim((string) $request->post['supplier']) !== '') {
            (new VendorRepository())->ensure((string) $request->post['supplier']);
        }
        $id = $this->repo->createFromInput($request->post);
        AuditService::record('create', 'asset', $id, null, $this->repo->find($id));
        // Bereits abgeschlossene (Vor-)Jahre sofort abschreiben.
        DepreciationService::bookDueYears($id);
        Session::flash('success', 'Anlagegut erfasst. Fällige AfA wurde gebucht.');
        $this->redirect('/anlagen/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $asset = $this->repo->find((int) $params['id']);
        if ($asset === null) {
            Response::notFound('Anlagegut nicht gefunden.');
            return;
        }
        $schedule = DepreciationService::schedule((int) $asset['cost_cents'], (string) $asset['acquired_date'], (int) $asset['useful_life_years'], (string) $asset['method']);
        $this->view('assets/show', [
            'title'    => 'Anlagegut ' . $asset['name'],
            'asset'    => $asset,
            'schedule' => $schedule,
            'booked'   => DepreciationService::bookedYears((int) $asset['id']),
            'currentYear' => (int) date('Y'),
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $asset = $this->repo->find((int) $params['id']);
        if ($asset === null) {
            Response::notFound('Anlagegut nicht gefunden.');
            return;
        }
        $this->view('assets/form', [
            'title'  => 'Anlagegut bearbeiten',
            'asset'  => $asset,
            'lifes'  => AssetRepository::usefulLifeSuggestions(),
            'action' => '/anlagen/' . $asset['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id     = (int) $params['id'];
        $before = $this->repo->find($id);
        if ($before === null) {
            Response::notFound('Anlagegut nicht gefunden.');
            return;
        }
        $request->post['supplier'] = pick_value($request->str('supplier'), $request->str('supplier_custom'));
        if (trim((string) $request->post['supplier']) !== '') {
            (new VendorRepository())->ensure((string) $request->post['supplier']);
        }
        $this->repo->updateFromInput($id, $request->post);
        AuditService::record('update', 'asset', $id, $before, $this->repo->find($id));
        Session::flash('success', 'Anlagegut aktualisiert. Bereits gebuchte AfA bleibt unverändert (GoBD).');
        $this->redirect('/anlagen/' . $id);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id    = (int) $params['id'];
        $asset = $this->repo->find($id);
        if ($asset === null) {
            Response::notFound('Anlagegut nicht gefunden.');
            return;
        }
        $booked = DepreciationService::bookedYears($id);
        if ($booked !== []) {
            Session::flash('error', 'Anlagegut kann nicht gelöscht werden: es bestehen bereits AfA-Buchungen (GoBD). Bei Abgang bitte einen entsprechenden Vorgang erfassen.');
            $this->redirect('/anlagen/' . $id);
        }
        $this->repo->delete($id);
        AuditService::record('delete', 'asset', $id, $asset, null);
        Session::flash('success', 'Anlagegut gelöscht.');
        $this->redirect('/anlagen');
    }

    /** @return array<string,mixed> */
    private function emptyAsset(): array
    {
        return [
            'id' => null, 'name' => '', 'supplier' => '', 'acquired_date' => date('Y-m-d'),
            'cost_cents' => 0, 'useful_life_years' => 3, 'method' => 'linear', 'note' => '',
        ];
    }
}
