<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Acl;
use Nova\Core\Auth;
use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\UserRepository;
use Nova\Services\AuditService;
use Nova\Services\Mailer;

/**
 * Benutzerverwaltung (nur Admin). Neue Benutzer erhalten einen Einladungslink
 * per E-Mail, über den sie ihr Passwort setzen. Benutzer werden deaktiviert
 * statt gelöscht.
 */
final class UserController extends Controller
{
    private UserRepository $repo;

    public function __construct()
    {
        $this->repo = new UserRepository();
    }

    public function index(Request $request): void
    {
        $this->view('users/list', [
            'title' => 'Benutzer',
            'users' => $this->repo->allOrdered(),
            'roles' => Acl::ROLES,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('users/form', [
            'title' => 'Neuer Benutzer',
            'user'  => ['id' => null, 'name' => '', 'email' => '', 'role' => 'staff', 'is_active' => 1],
            'roles' => Acl::ROLES,
            'action' => '/benutzer',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);

        $name  = $request->str('name');
        $email = $request->str('email');
        $role  = $request->str('role', 'staff');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Bitte eine gültige E-Mail-Adresse angeben.');
            $this->redirect('/benutzer/neu');
        }
        if (!Acl::isRole($role)) {
            $role = 'staff';
        }
        if ($this->repo->findByEmail($email) !== null) {
            Session::flash('error', 'Diese E-Mail ist bereits vergeben.');
            $this->redirect('/benutzer/neu');
        }

        // Zufälliges (vorerst unbenutzbares) Passwort; gesetzt wird es per Einladung.
        $id = $this->repo->createWithRole($email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $name ?: $email, $role);
        AuditService::record('create', 'user', $id, null, ['email' => $email, 'role' => $role]);

        $sent = $this->sendInvite($id, $email, $name);
        Session::flash($sent ? 'success' : 'warn', $sent
            ? "Benutzer angelegt. Einladung an {$email} versendet."
            : 'Benutzer angelegt, aber die Einladung konnte nicht versendet werden (E-Mail prüfen). Du kannst sie unter „Bearbeiten" erneut senden.');
        $this->redirect('/benutzer/' . $id . '/bearbeiten');
    }

    public function edit(Request $request, array $params): void
    {
        $user = $this->repo->find((int) $params['id']);
        if ($user === null) {
            Response::notFound('Benutzer nicht gefunden.');
            return;
        }
        $this->view('users/form', [
            'title'  => 'Benutzer bearbeiten',
            'user'   => $user,
            'roles'  => Acl::ROLES,
            'action' => '/benutzer/' . $user['id'],
            'isSelf' => (int) $user['id'] === Auth::id(),
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id   = (int) $params['id'];
        $user = $this->repo->find($id);
        if ($user === null) {
            Response::notFound('Benutzer nicht gefunden.');
            return;
        }

        $name = $request->str('name');
        $role = $request->str('role', $user['role']);
        if (!Acl::isRole($role)) {
            $role = (string) $user['role'];
        }

        // Schutz: den letzten aktiven Admin nicht herabstufen.
        if ($user['role'] === 'admin' && $role !== 'admin' && $this->repo->activeAdminCount() <= 1) {
            Session::flash('error', 'Der letzte aktive Admin kann nicht herabgestuft werden.');
            $this->redirect('/benutzer/' . $id . '/bearbeiten');
        }

        $this->repo->updateName($id, $name ?: (string) $user['email']);
        $this->repo->setRole($id, $role);
        AuditService::record('update', 'user', $id, ['role' => $user['role']], ['role' => $role]);

        Session::flash('success', 'Benutzer aktualisiert.');
        $this->redirect('/benutzer/' . $id . '/bearbeiten');
    }

    public function toggleActive(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id   = (int) $params['id'];
        $user = $this->repo->find($id);
        if ($user === null) {
            Response::notFound('Benutzer nicht gefunden.');
            return;
        }
        $activate = !((int) $user['is_active'] === 1);

        if (!$activate) {
            if ($id === Auth::id()) {
                Session::flash('error', 'Du kannst dein eigenes Konto nicht deaktivieren.');
                $this->redirect('/benutzer/' . $id . '/bearbeiten');
            }
            if ($user['role'] === 'admin' && $this->repo->activeAdminCount() <= 1) {
                Session::flash('error', 'Der letzte aktive Admin kann nicht deaktiviert werden.');
                $this->redirect('/benutzer/' . $id . '/bearbeiten');
            }
        }

        $this->repo->setActive($id, $activate);
        AuditService::record('update', 'user', $id, null, ['is_active' => $activate ? 1 : 0]);
        Session::flash('success', $activate ? 'Benutzer aktiviert.' : 'Benutzer deaktiviert (Login gesperrt).');
        $this->redirect('/benutzer/' . $id . '/bearbeiten');
    }

    public function resendInvite(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id   = (int) $params['id'];
        $user = $this->repo->find($id);
        if ($user === null) {
            Response::notFound('Benutzer nicht gefunden.');
            return;
        }
        $sent = $this->sendInvite($id, (string) $user['email'], (string) $user['name']);
        Session::flash($sent ? 'success' : 'error', $sent
            ? 'Einladung erneut versendet.'
            : 'Einladung konnte nicht versendet werden – bitte E-Mail-Einstellungen prüfen.');
        $this->redirect('/benutzer/' . $id . '/bearbeiten');
    }

    /** Erzeugt einen Einladungs-/Passwort-Token und versendet den Link. */
    private function sendInvite(int $id, string $email, string $name): bool
    {
        $token = bin2hex(random_bytes(32));
        $this->repo->setResetToken($id, hash('sha256', $token), date('Y-m-d H:i:s', time() + 7 * 86400));

        $settings = (new CompanySettingsRepository())->get();
        $link     = $this->baseUrl() . '/passwort-zuruecksetzen?token=' . $token;
        $body = implode("\n", [
            'Hallo' . ($name !== '' ? ' ' . $name : '') . ',',
            '',
            'für dich wurde ein Zugang zu ' . ($settings['company_name'] ?: 'Nova') . ' angelegt.',
            'Bitte lege über folgenden Link dein Passwort fest (7 Tage gültig):',
            '',
            $link,
            '',
            'Anschließend kannst du dich mit deiner E-Mail-Adresse anmelden.',
        ]);

        try {
            Mailer::send($settings, $email, $name, 'Dein Zugang zu ' . ($settings['company_name'] ?: 'Nova'), $body);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) ($GLOBALS['nova_config']['app_url'] ?? ''), '/');
        if ($url !== '') {
            return $url;
        }
        $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
