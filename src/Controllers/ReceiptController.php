<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\DB;
use Nova\Core\Format;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\ExpenseRepository;
use Nova\Models\ReceiptRepository;
use Nova\Services\AuditService;
use Nova\Services\ReceiptService;

final class ReceiptController extends Controller
{
    private ReceiptRepository $repo;

    public function __construct()
    {
        $this->repo = new ReceiptRepository();
    }

    public function index(Request $request): void
    {
        $term = $request->str('q');
        $this->view('receipts/list', [
            'title'    => 'Belege',
            'receipts' => $this->repo->search($term),
            'q'        => $term,
            'expenses' => $this->linkableExpenses(),
            'invoices' => $this->linkableInvoices(),
        ]);
    }

    /**
     * Ordnet einen (noch freien) Beleg einer Ausgabe oder Rechnung zu und
     * archiviert ihn dadurch (locked = 1). Eingabe-Format: "expense:42".
     */
    public function link(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $receipt = $this->repo->find($id);
        if ($receipt === null) {
            Response::notFound('Beleg nicht gefunden.');
            return;
        }
        if ((int) $receipt['locked'] === 1) {
            Session::flash('error', 'Beleg ist bereits zugeordnet und archiviert.');
            $this->redirect('/belege');
        }

        $target = $request->str('target'); // "expense:42" | "invoice:42"
        [$type, $targetId] = array_pad(explode(':', $target, 2), 2, '');
        $targetId = (int) $targetId;

        if (!in_array($type, ['expense', 'invoice'], true) || $targetId === 0) {
            Session::flash('error', 'Bitte eine Ausgabe oder Rechnung zum Zuordnen wählen.');
            $this->redirect('/belege');
        }

        $table  = $type === 'expense' ? 'expense' : 'invoice';
        $exists = (int) DB::getInstance()->fetchColumn(
            "SELECT COUNT(*) FROM {$table} WHERE id = :id",
            ['id' => $targetId]
        );
        if ($exists === 0) {
            Session::flash('error', 'Das Zuordnungsziel existiert nicht.');
            $this->redirect('/belege');
        }

        $this->repo->link($id, $type, $targetId);
        AuditService::record('update', 'receipt', $id, $receipt, ['linkable_type' => $type, 'linkable_id' => $targetId]);

        $label = $type === 'expense' ? 'Ausgabe' : 'Rechnung';
        Session::flash('success', "Beleg der {$label} #{$targetId} zugeordnet und archiviert.");
        $this->redirect('/belege');
    }

    /** @return array<int,array<string,mixed>> Ausgaben als Auswahl für die Zuordnung. */
    private function linkableExpenses(): array
    {
        return DB::getInstance()->fetchAll(
            'SELECT id, expense_date, supplier, amount_cents
             FROM expense ORDER BY expense_date DESC, id DESC LIMIT 200'
        );
    }

    /** @return array<int,array<string,mixed>> Rechnungen als Auswahl für die Zuordnung. */
    private function linkableInvoices(): array
    {
        return DB::getInstance()->fetchAll(
            "SELECT i.id, i.number, i.invoice_date, i.gross_total_cents, c.company_name, c.contact_name
             FROM invoice i JOIN customer c ON c.id = i.customer_id
             ORDER BY i.created_at DESC LIMIT 200"
        );
    }

    public function create(Request $request): void
    {
        $this->view('receipts/form', ['title' => 'Beleg hochladen']);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);

        $files = ReceiptService::normalizeUploads($request->files['receipt'] ?? null);
        if ($files === []) {
            Session::flash('error', 'Bitte mindestens eine Datei auswählen oder ein Foto aufnehmen.');
            $this->redirect('/belege/neu');
        }

        $type = $request->str('type', 'sonstiges');

        // Optional: direkt als Ausgabe verbuchen und die Belege daran hängen.
        $expenseId   = null;
        $createdExp  = false;
        if ($request->bool('as_expense') && Format::toCents($request->str('amount')) > 0) {
            $supplier = pick_value($request->str('supplier'), $request->str('supplier_custom'));
            (new \Nova\Models\VendorRepository())->ensure($supplier);
            $expenseId = (new ExpenseRepository())->createFromInput([
                'expense_date' => $request->str('expense_date'),
                'supplier'     => $supplier,
                'tax_category' => $request->str('tax_category'),
                'category'     => $request->str('tax_category'),
                'amount'       => $request->str('amount'),
                'method'       => pick_value($request->str('method'), $request->str('method_custom')),
                'status'       => $request->str('status', 'paid'),
            ]);
            AuditService::record('create', 'expense', $expenseId, null, ['via' => 'beleg-upload']);
            $createdExp = true;
        }

        $saved = 0;
        foreach ($files as $file) {
            try {
                $meta = ReceiptService::storeReceipt($file);
                $id   = $this->repo->createFromUpload($meta, $type, $expenseId !== null ? 'expense' : null, $expenseId);
                AuditService::record('create', 'receipt', $id, null, ['name' => $meta['original_name']]);
                $saved++;
            } catch (\RuntimeException $e) {
                Session::flash('warn', 'Eine Datei wurde übersprungen: ' . $e->getMessage());
            }
        }

        if ($saved === 0) {
            Session::flash('error', 'Kein Beleg konnte gespeichert werden.');
            $this->redirect('/belege/neu');
        }

        if ($createdExp) {
            Session::flash('success', ($saved === 1 ? 'Beleg' : $saved . ' Belege') . ' hochgeladen und als Ausgabe verbucht.');
            $this->redirect('/ausgaben/' . $expenseId);
        }
        Session::flash('success', $saved === 1 ? 'Beleg hochgeladen.' : $saved . ' Belege hochgeladen.');
        $this->redirect('/belege');
    }

    /**
     * Authentifizierter Download – Dateien liegen außerhalb des Web-Roots.
     */
    public function download(Request $request, array $params): void
    {
        $receipt = $this->repo->find((int) $params['id']);
        if ($receipt === null) {
            Response::notFound('Beleg nicht gefunden.');
            return;
        }
        $abs = ($GLOBALS['nova_config']['paths']['receipts'] ?? '') . '/' . $receipt['stored_path'];
        if (!is_file($abs)) {
            Response::notFound('Belegdatei fehlt.');
            return;
        }
        Response::inline($abs, $receipt['mime']);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $receipt = $this->repo->find($id);
        if ($receipt === null) {
            Response::notFound('Beleg nicht gefunden.');
            return;
        }
        if ((int) $receipt['locked'] === 1) {
            Session::flash('error', 'Zugeordnete Belege sind archiviert und können nicht gelöscht werden.');
            $this->redirect('/belege');
        }

        $abs = ($GLOBALS['nova_config']['paths']['receipts'] ?? '') . '/' . $receipt['stored_path'];
        if (is_file($abs)) {
            @unlink($abs);
        }
        $this->repo->delete($id);
        AuditService::record('delete', 'receipt', $id, $receipt, null);

        Session::flash('success', 'Beleg gelöscht.');
        $this->redirect('/belege');
    }
}
