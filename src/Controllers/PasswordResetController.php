<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Auth;
use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Session;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\UserRepository;
use Nova\Services\AuditService;
use Nova\Services\Mailer;

/**
 * Passwort-Zurücksetzen per E-Mail-Token. Token wird nur als SHA-256-Hash
 * gespeichert, ist 1 Stunde gültig und nach Gebrauch entwertet.
 */
final class PasswordResetController extends Controller
{
    private const NEUTRAL = 'Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen gesendet.';

    public function showForgot(Request $request): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $this->view('auth/forgot', ['title' => 'Passwort vergessen'], layout: null);
    }

    public function sendLink(Request $request): void
    {
        $this->verifyCsrf($request);
        $email = $request->str('email');

        $users = new UserRepository();
        $user  = $users->findByEmail($email);

        if ($user !== null) {
            $token = bin2hex(random_bytes(32));
            $users->setResetToken(
                (int) $user['id'],
                hash('sha256', $token),
                date('Y-m-d H:i:s', time() + 3600)
            );

            $settings = (new CompanySettingsRepository())->get();
            $link     = $this->baseUrl() . '/passwort-zuruecksetzen?token=' . $token;
            $body = implode("\n", [
                'Hallo,',
                '',
                'für dein Nova-Konto wurde das Zurücksetzen des Passworts angefordert.',
                'Über folgenden Link kannst du ein neues Passwort vergeben (1 Stunde gültig):',
                '',
                $link,
                '',
                'Wenn du das nicht warst, ignoriere diese E-Mail einfach.',
            ]);

            try {
                Mailer::send($settings, $email, (string) ($user['name'] ?? ''), 'Passwort zurücksetzen – Nova', $body);
                AuditService::record('password_reset_request', 'user', (int) $user['id']);
            } catch (\RuntimeException $e) {
                Session::flash('error', 'E-Mail-Versand nicht möglich: ' . $e->getMessage()
                    . ' Tipp: Admin kann das Passwort per CLI zurücksetzen (php bin/reset-password.php).');
                $this->redirect('/passwort-vergessen');
            }
        }

        Session::flash('success', self::NEUTRAL);
        $this->redirect('/login');
    }

    public function showReset(Request $request): void
    {
        $token = $request->str('token');
        $user  = (new UserRepository())->findByValidResetToken(hash('sha256', $token));
        if ($token === '' || $user === null) {
            Session::flash('error', 'Der Link ist ungültig oder abgelaufen. Bitte fordere einen neuen an.');
            $this->redirect('/passwort-vergessen');
        }
        $this->view('auth/reset', ['title' => 'Neues Passwort', 'token' => $token], layout: null);
    }

    public function doReset(Request $request): void
    {
        $this->verifyCsrf($request);

        $token    = $request->str('token');
        $password = $request->str('password');
        $confirm  = $request->str('password_confirm');

        $users = new UserRepository();
        $user  = $users->findByValidResetToken(hash('sha256', $token));
        if ($user === null) {
            Session::flash('error', 'Der Link ist ungültig oder abgelaufen.');
            $this->redirect('/passwort-vergessen');
        }
        if (strlen($password) < 8) {
            Session::flash('error', 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            $this->redirect('/passwort-zuruecksetzen?token=' . urlencode($token));
        }
        if ($password !== $confirm) {
            Session::flash('error', 'Die Passwörter stimmen nicht überein.');
            $this->redirect('/passwort-zuruecksetzen?token=' . urlencode($token));
        }

        $users->resetPassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        AuditService::record('password_reset', 'user', (int) $user['id']);

        Session::flash('success', 'Passwort geändert. Du kannst dich jetzt anmelden.');
        $this->redirect('/login');
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) ($GLOBALS['nova_config']['app_url'] ?? ''), '/');
        if ($url !== '') {
            return $url;
        }
        $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
