<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Session;
use Nova\Services\AuditService;
use Nova\Services\UpdateService;

final class UpdateController extends Controller
{
    /** Erzwingt eine GitHub-Abfrage und kehrt zu den Einstellungen zurück. */
    public function checkNow(Request $request): void
    {
        $this->verifyCsrf($request);
        $result = UpdateService::check(true);

        if (!empty($result['error'])) {
            Session::flash('error', 'Update-Prüfung fehlgeschlagen: ' . $result['error']);
        } elseif (!empty($result['has_update'])) {
            Session::flash('success', 'Neue Version verfügbar: ' . $result['latest'] . '.');
        } else {
            Session::flash('success', 'Nova ist aktuell (' . $result['current'] . ').');
        }
        $this->redirect('/einstellungen');
    }

    /** Voll-automatisches 1-Klick-Update (Backup → ZIP → Migration). */
    public function install(Request $request): void
    {
        $this->verifyCsrf($request);
        $info = UpdateService::check(true);

        if (empty($info['has_update']) || empty($info['zip_url'])) {
            Session::flash('warn', 'Kein installierbares Update gefunden.');
            $this->redirect('/einstellungen');
        }

        try {
            $log = \Nova\Services\UpdateInstaller::run((string) $info['zip_url'], $GLOBALS['nova_config']);
            AuditService::record('update', 'system', 1, ['from' => $info['current']], ['to' => $info['latest']]);
            Session::flash('success', 'Update auf ' . $info['latest'] . ' installiert. ' . implode(' · ', $log));
        } catch (\Throwable $e) {
            Session::flash('error', 'Update fehlgeschlagen: ' . $e->getMessage() . ' Es wurde vorab ein Backup angelegt.');
        }
        $this->redirect('/einstellungen');
    }
}
