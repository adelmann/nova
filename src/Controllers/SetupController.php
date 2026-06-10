<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Auth;
use Nova\Core\Controller;
use Nova\Core\DB;
use Nova\Core\Request;
use Nova\Core\Session;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\UserRepository;
use Nova\Services\MigrationRunner;

/**
 * Web-Setup für die Erstinstallation: legt das Datenbankschema an, erstellt den
 * ersten Admin-Benutzer und erfasst die Firmen-Basisdaten.
 */
final class SetupController extends Controller
{
    public function index(Request $request): void
    {
        if (self::isInstalled()) {
            $this->redirect('/login');
        }
        // Schema sicherstellen, damit der frische Upload sofort nutzbar ist.
        MigrationRunner::run($GLOBALS['nova_config']['paths']['migrations']);

        $this->view('setup/index', ['title' => 'Einrichtung'], layout: null);
    }

    public function store(Request $request): void
    {
        if (self::isInstalled()) {
            $this->redirect('/login');
        }
        $this->verifyCsrf($request);

        MigrationRunner::run($GLOBALS['nova_config']['paths']['migrations']);

        $name     = $request->str('name', 'Admin');
        $email    = $request->str('email');
        $password = $request->str('password');
        $confirm  = $request->str('password_confirm');
        $company  = $request->str('company_name');

        $errors = [];
        if (!$request->bool('accept_terms')) {
            $errors[] = 'Bitte den Haftungsausschluss bestätigen.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Die Passwörter stimmen nicht überein.';
        }
        if ($company === '') {
            $errors[] = 'Bitte einen Firmennamen angeben.';
        }
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/setup');
        }

        $users = new UserRepository();
        if ($users->findByEmail($email) !== null) {
            Session::flash('error', 'Diese E-Mail ist bereits vergeben.');
            $this->redirect('/setup');
        }

        $users->create($email, password_hash($password, PASSWORD_DEFAULT), $name ?: 'Admin');

        (new CompanySettingsRepository())->update([
            'company_name'        => $company,
            'owner_name'          => $request->str('owner_name'),
            'address_line1'       => $request->str('address_line1'),
            'zip'                 => $request->str('zip'),
            'city'                => $request->str('city'),
            'email'               => $request->str('company_email'),
            'is_kleinunternehmer' => $request->bool('is_kleinunternehmer') ? 1 : 0,
        ]);

        // Direkt anmelden und auf die Konto-Seite, um 2FA nahezulegen.
        Auth::attempt($email, $password);
        Session::flash('success', 'Einrichtung abgeschlossen. Willkommen bei Nova!');
        Session::flash('warn', 'Sicherheits-Empfehlung: Aktiviere jetzt die Zwei-Faktor-Authentifizierung (siehe unten).');
        $this->redirect('/konto');
    }

    /** Installiert = Schema vorhanden und mindestens ein Benutzer. */
    public static function isInstalled(): bool
    {
        try {
            return (int) DB::getInstance()->fetchColumn('SELECT COUNT(*) FROM user') > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
