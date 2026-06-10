<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\CustomerRepository;
use Nova\Services\AuditService;

final class CustomerController extends Controller
{
    private CustomerRepository $repo;

    public function __construct()
    {
        $this->repo = new CustomerRepository();
    }

    public function index(Request $request): void
    {
        $term        = $request->str('q');
        $showArchived = $request->bool('archiv');
        $this->view('customers/list', [
            'title'        => 'Kunden',
            'customers'    => $this->repo->search($term, $showArchived),
            'q'            => $term,
            'showArchived' => $showArchived,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('customers/form', [
            'title'    => 'Neuer Kunde',
            'customer' => $this->emptyCustomer(),
            'action'   => '/kunden',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);

        $errors = $this->validateInput($request);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->view('customers/form', [
                'title'    => 'Neuer Kunde',
                'customer' => array_merge($this->emptyCustomer(), $request->post),
                'action'   => '/kunden',
            ]);
            return;
        }

        $id = $this->repo->createFromInput($request->post);
        AuditService::record('create', 'customer', $id, null, $this->repo->find($id));

        Session::flash('success', 'Kunde angelegt.');
        $this->redirect('/kunden/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $customer = $this->repo->find((int) $params['id']);
        if ($customer === null) {
            Response::notFound('Kunde nicht gefunden.');
            return;
        }
        $this->view('customers/show', [
            'title'    => $customer['company_name'] ?: $customer['contact_name'],
            'customer' => $customer,
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $customer = $this->repo->find((int) $params['id']);
        if ($customer === null) {
            Response::notFound('Kunde nicht gefunden.');
            return;
        }
        $this->view('customers/form', [
            'title'    => 'Kunde bearbeiten',
            'customer' => $customer,
            'action'   => '/kunden/' . $customer['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id     = (int) $params['id'];
        $before = $this->repo->find($id);
        if ($before === null) {
            Response::notFound('Kunde nicht gefunden.');
            return;
        }

        $errors = $this->validateInput($request);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->view('customers/form', [
                'title'    => 'Kunde bearbeiten',
                'customer' => array_merge($before, $request->post),
                'action'   => '/kunden/' . $id,
            ]);
            return;
        }

        $this->repo->updateFromInput($id, $request->post);
        AuditService::record('update', 'customer', $id, $before, $this->repo->find($id));

        Session::flash('success', 'Kunde aktualisiert.');
        $this->redirect('/kunden/' . $id);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id       = (int) $params['id'];
        $customer = $this->repo->find($id);
        if ($customer === null) {
            Response::notFound('Kunde nicht gefunden.');
            return;
        }

        if ($this->repo->countReferences($id) > 0) {
            Session::flash('error', 'Kunde hat noch zugeordnete Projekte, Angebote oder Rechnungen und kann nicht gelöscht werden.');
            $this->redirect('/kunden/' . $id);
        }

        $this->repo->delete($id);
        AuditService::record('delete', 'customer', $id, $customer, null);

        Session::flash('success', 'Kunde gelöscht.');
        $this->redirect('/kunden');
    }

    public function archive(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id = (int) $params['id'];
        if ($this->repo->find($id) === null) {
            Response::notFound('Kunde nicht gefunden.');
            return;
        }
        $this->repo->archive($id);
        AuditService::record('update', 'customer', $id, null, ['archived' => true]);
        Session::flash('success', 'Kunde archiviert – er ist aus Listen und Auswahlfeldern ausgeblendet.');
        $this->redirect('/kunden/' . $id);
    }

    public function unarchive(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $id = (int) $params['id'];
        if ($this->repo->find($id) === null) {
            Response::notFound('Kunde nicht gefunden.');
            return;
        }
        $this->repo->unarchive($id);
        AuditService::record('update', 'customer', $id, null, ['archived' => false]);
        Session::flash('success', 'Kunde wiederhergestellt.');
        $this->redirect('/kunden/' . $id);
    }

    /** @return array<string,string> */
    private function validateInput(Request $request): array
    {
        $errors = [];
        $name   = $request->str('company_name');
        $contact = $request->str('contact_name');
        if ($name === '' && $contact === '') {
            $errors[] = 'Bitte Firmenname oder Ansprechpartner angeben.';
        }
        $email = $request->str('email');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-Mail-Adresse ist ungültig.';
        }
        return $errors;
    }

    /** @return array<string,mixed> */
    private function emptyCustomer(): array
    {
        return [
            'id' => null, 'company_name' => '', 'contact_name' => '',
            'address_line1' => '', 'address_line2' => '', 'zip' => '', 'city' => '',
            'country' => 'Deutschland', 'email' => '', 'phone' => '', 'vat_id' => '',
            'type' => 'business', 'notes' => '',
        ];
    }
}
