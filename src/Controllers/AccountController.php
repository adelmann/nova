<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Auth;
use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Session;
use Nova\Models\UserRepository;
use Nova\Services\AuditService;
use Nova\Services\Totp;

/**
 * Kontoverwaltung des angemeldeten Benutzers: E-Mail und Passwort ändern.
 * Beide Aktionen erfordern die Bestätigung durch das aktuelle Passwort.
 */
final class AccountController extends Controller
{
    public function edit(Request $request): void
    {
        $this->view('account/edit', [
            'title' => 'Konto',
            'user'  => Auth::user(),
        ]);
    }

    public function updateEmail(Request $request): void
    {
        $this->verifyCsrf($request);

        $user  = Auth::user();
        $repo  = new UserRepository();
        $email = $request->str('email');
        $pass  = $request->str('current_password');

        if (!password_verify($pass, $user['password_hash'])) {
            Session::flash('error', 'Das aktuelle Passwort ist falsch.');
            $this->redirect('/konto');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Bitte eine gültige E-Mail-Adresse angeben.');
            $this->redirect('/konto');
        }
        if ($repo->emailTakenByOther($email, (int) $user['id'])) {
            Session::flash('error', 'Diese E-Mail-Adresse wird bereits verwendet.');
            $this->redirect('/konto');
        }

        if ($email !== $user['email']) {
            $repo->updateEmail((int) $user['id'], $email);
            AuditService::record(
                'update',
                'user',
                (int) $user['id'],
                ['email' => $user['email']],
                ['email' => $email]
            );
            Session::flash('success', 'E-Mail-Adresse geändert.');
        } else {
            Session::flash('success', 'E-Mail-Adresse unverändert.');
        }

        $this->redirect('/konto');
    }

    public function updatePassword(Request $request): void
    {
        $this->verifyCsrf($request);

        $user    = Auth::user();
        $repo    = new UserRepository();
        $current = $request->str('current_password');
        $new     = $request->str('new_password');
        $confirm = $request->str('new_password_confirm');

        if (!password_verify($current, $user['password_hash'])) {
            Session::flash('error', 'Das aktuelle Passwort ist falsch.');
            $this->redirect('/konto');
        }
        if (strlen($new) < 8) {
            Session::flash('error', 'Das neue Passwort muss mindestens 8 Zeichen lang sein.');
            $this->redirect('/konto');
        }
        if ($new !== $confirm) {
            Session::flash('error', 'Die Passwort-Bestätigung stimmt nicht überein.');
            $this->redirect('/konto');
        }

        $repo->updatePassword((int) $user['id'], password_hash($new, PASSWORD_DEFAULT));
        // Audit ohne den Passwort-Inhalt (nur die Tatsache der Änderung).
        AuditService::record('update', 'user', (int) $user['id'], null, ['password_changed' => true]);

        Session::flash('success', 'Passwort geändert.');
        $this->redirect('/konto');
    }

    /** Startet die 2FA-Einrichtung: Secret + Recovery-Codes erzeugen und anzeigen. */
    public function start2fa(Request $request): void
    {
        $this->verifyCsrf($request);
        $user = Auth::user();
        if ((int) ($user['totp_enabled'] ?? 0) === 1) {
            Session::flash('warn', 'Zwei-Faktor-Authentifizierung ist bereits aktiv.');
            $this->redirect('/konto');
        }

        $secret   = Totp::generateSecret();
        $recovery = Totp::generateRecoveryCodes();
        Session::set('_2fa_setup_secret', $secret);
        Session::set('_2fa_setup_recovery', $recovery);

        $issuer = (string) ($GLOBALS['nova_config']['app_name'] ?? 'Nova');
        $this->view('account/twofactor_setup', [
            'title'    => '2FA einrichten',
            'secret'   => $secret,
            'uri'      => Totp::uri($secret, (string) $user['email'], $issuer),
            'recovery' => $recovery,
        ]);
    }

    /** Schließt die Einrichtung ab, nachdem ein gültiger Code eingegeben wurde. */
    public function enable2fa(Request $request): void
    {
        $this->verifyCsrf($request);
        $user     = Auth::user();
        $secret   = (string) Session::get('_2fa_setup_secret', '');
        $recovery = Session::get('_2fa_setup_recovery', []);

        if ($secret === '' || !is_array($recovery)) {
            Session::flash('error', 'Die Einrichtung ist abgelaufen. Bitte erneut starten.');
            $this->redirect('/konto');
        }
        if (!Totp::verify($secret, $request->str('code'))) {
            Session::flash('error', 'Der Code war nicht korrekt. Bitte erneut starten.');
            $this->redirect('/konto');
        }

        $hashes = array_map(static fn (string $c): string => password_hash($c, PASSWORD_DEFAULT), $recovery);
        (new UserRepository())->enableTotp((int) $user['id'], $secret, $hashes);
        Session::forget('_2fa_setup_secret');
        Session::forget('_2fa_setup_recovery');
        AuditService::record('update', 'user', (int) $user['id'], null, ['totp_enabled' => true]);

        Session::flash('success', 'Zwei-Faktor-Authentifizierung ist jetzt aktiv.');
        $this->redirect('/konto');
    }

    /** Deaktiviert 2FA (Bestätigung per aktuellem Passwort). */
    public function disable2fa(Request $request): void
    {
        $this->verifyCsrf($request);
        $user = Auth::user();

        if (!password_verify($request->str('current_password'), $user['password_hash'])) {
            Session::flash('error', 'Das aktuelle Passwort ist falsch.');
            $this->redirect('/konto');
        }

        (new UserRepository())->disableTotp((int) $user['id']);
        AuditService::record('update', 'user', (int) $user['id'], null, ['totp_enabled' => false]);

        Session::flash('success', 'Zwei-Faktor-Authentifizierung wurde deaktiviert.');
        $this->redirect('/konto');
    }
}
