<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Auth;
use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Session;
use Nova\Models\CompanySettingsRepository;
use Nova\Services\AuditService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $settings = (new CompanySettingsRepository())->get();
        $this->view('auth/login', [
            'title'       => 'Anmelden',
            'imprint_url' => $settings['imprint_url'] ?? '',
            'privacy_url' => $settings['privacy_url'] ?? '',
        ], layout: null);
    }

    public function login(Request $request): void
    {
        $this->verifyCsrf($request);

        $email    = $request->str('email');
        $password = $request->str('password');

        $user = Auth::verify($email, $password);
        if ($user === null) {
            Session::flash('error', 'E-Mail oder Passwort ist falsch.');
            $this->redirect('/login');
        }
        if ((int) ($user['is_active'] ?? 1) !== 1) {
            Session::flash('error', 'Dieses Konto ist deaktiviert.');
            $this->redirect('/login');
        }

        // Zweiter Faktor erforderlich? Dann erst nach Code-Eingabe anmelden.
        if ((int) ($user['totp_enabled'] ?? 0) === 1) {
            Session::set('_2fa_user', (int) $user['id']);
            $this->redirect('/login/2fa');
        }

        Auth::loginAs((int) $user['id']);
        AuditService::record('login', 'user', (int) $user['id']);

        $intended = Session::get('_intended', '/');
        Session::forget('_intended');
        $this->redirect(is_string($intended) ? $intended : '/');
    }

    public function show2fa(Request $request): void
    {
        if (Session::get('_2fa_user') === null) {
            $this->redirect('/login');
        }
        $this->view('auth/twofactor', ['title' => 'Bestätigung'], layout: null);
    }

    public function verify2fa(Request $request): void
    {
        $this->verifyCsrf($request);

        $pending = Session::get('_2fa_user');
        if ($pending === null) {
            $this->redirect('/login');
        }
        $userId = (int) $pending;
        $repo   = new \Nova\Models\UserRepository();
        $user   = $repo->find($userId);
        if ($user === null) {
            Session::forget('_2fa_user');
            $this->redirect('/login');
        }

        $code = $request->str('code');
        $ok   = \Nova\Services\Totp::verify((string) $user['totp_secret'], $code)
            || $repo->consumeRecoveryCode($userId, trim($code));

        if (!$ok) {
            Session::flash('error', 'Code ungültig. Bitte erneut versuchen.');
            $this->redirect('/login/2fa');
        }

        Session::forget('_2fa_user');
        Auth::loginAs($userId);
        AuditService::record('login', 'user', $userId, null, ['2fa' => true]);

        $intended = Session::get('_intended', '/');
        Session::forget('_intended');
        $this->redirect(is_string($intended) ? $intended : '/');
    }

    public function logout(Request $request): void
    {
        $this->verifyCsrf($request);
        Auth::logout();
        Session::flash('success', 'Du wurdest abgemeldet.');
        $this->redirect('/login');
    }
}
