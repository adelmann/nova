<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\DB;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Models\InvoiceRepository;

/**
 * Offene-Posten-Liste (OPOS) und Kunden-Kontoauszug. Reine Auswertung über
 * finalisierte Rechnungen und erfasste Zahlungen – keine Buchungen.
 */
final class ReceivablesController extends Controller
{
    public function index(Request $request): void
    {
        $db = DB::getInstance();
        (new InvoiceRepository())->markOverdue();
        $today = date('Y-m-d');

        $rows = $db->fetchAll(
            "SELECT i.id, i.number, i.invoice_date, i.due_date, i.status,
                    i.gross_total_cents, i.paid_total_cents,
                    (i.gross_total_cents - i.paid_total_cents) AS open_cents,
                    c.id AS customer_id, c.company_name, c.contact_name
             FROM invoice i JOIN customer c ON c.id = i.customer_id
             WHERE i.is_locked = 1 AND i.status IN ('sent','overdue')
               AND (i.gross_total_cents - i.paid_total_cents) > 0
             ORDER BY c.company_name, c.contact_name, i.due_date, i.invoice_date"
        );

        // Aging-Eimer + Gruppierung je Kunde.
        $buckets = ['not_due' => 0, 'd1_30' => 0, 'd31_60' => 0, 'd60p' => 0];
        $total   = 0;
        $groups  = [];
        foreach ($rows as $r) {
            $open = (int) $r['open_cents'];
            $total += $open;
            $days = ($r['due_date'] && $r['due_date'] < $today)
                ? (int) floor((strtotime($today) - strtotime((string) $r['due_date'])) / 86400)
                : 0;
            $r['days_overdue'] = $days;
            if ($days <= 0)      { $buckets['not_due'] += $open; }
            elseif ($days <= 30) { $buckets['d1_30']  += $open; }
            elseif ($days <= 60) { $buckets['d31_60'] += $open; }
            else                 { $buckets['d60p']   += $open; }

            $cid = (int) $r['customer_id'];
            if (!isset($groups[$cid])) {
                $groups[$cid] = [
                    'customer_id' => $cid,
                    'name'        => (string) ($r['company_name'] ?: $r['contact_name']),
                    'open'        => 0,
                    'items'       => [],
                ];
            }
            $groups[$cid]['open'] += $open;
            $groups[$cid]['items'][] = $r;
        }

        $this->view('receivables/index', [
            'title'   => 'Offene Posten',
            'groups'  => array_values($groups),
            'buckets' => $buckets,
            'total'   => $total,
            'count'   => count($rows),
        ]);
    }

    public function statement(Request $request, array $params): void
    {
        $db  = DB::getInstance();
        $cid = (int) $params['id'];
        $customer = $db->fetch('SELECT * FROM customer WHERE id = :id', ['id' => $cid]);
        if ($customer === null) {
            Response::notFound('Kunde nicht gefunden.');
            return;
        }

        // Finalisierte Rechnungen (inkl. Storno) als Forderungs-Ereignisse.
        $invoices = $db->fetchAll(
            "SELECT id, number, invoice_date, status, gross_total_cents
             FROM invoice
             WHERE customer_id = :c AND is_locked = 1
             ORDER BY invoice_date, id",
            ['c' => $cid]
        );
        // Zahlungen zu diesen Rechnungen.
        $payments = $db->fetchAll(
            "SELECT p.paid_on, p.amount_cents, p.method, p.note, i.number
             FROM payment p JOIN invoice i ON i.id = p.invoice_id
             WHERE i.customer_id = :c
             ORDER BY p.paid_on, p.id",
            ['c' => $cid]
        );

        // Ereignisse zu einer chronologischen Liste mit laufendem Saldo mischen.
        $events = [];
        foreach ($invoices as $i) {
            $events[] = [
                'date'   => (string) $i['invoice_date'],
                'type'   => $i['status'] === 'cancelled' ? 'Storno' : 'Rechnung',
                'ref'    => (string) $i['number'],
                'amount' => (int) $i['gross_total_cents'], // erhöht die Forderung (Storno bereits negativ)
            ];
        }
        foreach ($payments as $p) {
            $events[] = [
                'date'   => (string) $p['paid_on'],
                'type'   => 'Zahlung' . ($p['method'] ? ' (' . $p['method'] . ')' : ''),
                'ref'    => (string) $p['number'],
                'amount' => -(int) $p['amount_cents'], // verringert die Forderung
            ];
        }
        usort($events, static fn ($a, $b) => [$a['date'], $a['type']] <=> [$b['date'], $b['type']]);

        $balance = 0;
        foreach ($events as &$ev) {
            $balance += $ev['amount'];
            $ev['balance'] = $balance;
        }
        unset($ev);

        $this->view('receivables/statement', [
            'title'    => 'Kontoauszug – ' . ($customer['company_name'] ?: $customer['contact_name']),
            'customer' => $customer,
            'events'   => $events,
            'balance'  => $balance,
        ]);
    }
}
