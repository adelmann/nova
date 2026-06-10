<?php

declare(strict_types=1);

use Nova\Controllers\AboutController;
use Nova\Controllers\AccountController;
use Nova\Controllers\AssistantController;
use Nova\Controllers\AuditController;
use Nova\Controllers\AuthController;
use Nova\Controllers\BankImportController;
use Nova\Controllers\CatalogController;
use Nova\Controllers\CronController;
use Nova\Controllers\CustomerController;
use Nova\Controllers\DashboardController;
use Nova\Controllers\ExpenseController;
use Nova\Controllers\ExportController;
use Nova\Controllers\IncomeController;
use Nova\Controllers\InvoiceController;
use Nova\Controllers\LedgerController;
use Nova\Controllers\PasswordResetController;
use Nova\Controllers\PaymentController;
use Nova\Controllers\ProjectController;
use Nova\Controllers\QuoteController;
use Nova\Controllers\ReceiptController;
use Nova\Controllers\RecurringController;
use Nova\Controllers\RecurringExpenseController;
use Nova\Controllers\ReminderController;
use Nova\Controllers\ReportController;
use Nova\Controllers\SettingsController;
use Nova\Controllers\SetupController;
use Nova\Controllers\UpdateController;
use Nova\Controllers\UserController;
use Nova\Controllers\VendorController;
use Nova\Core\Router;

/**
 * Zentrale Routen-Definition.
 *   3. Parameter $auth: Anmeldung erforderlich (Standard: true).
 *   4. Parameter $cap:  benötigte Capability (Acl), null = jeder Angemeldete.
 * GET = ansehen (view_accounting), POST = ändern (manage_*) – dadurch ist die
 * Rolle „Steuerberater" automatisch nur-lesend.
 */
return static function (Router $r): void {
    // Authentifizierung (öffentlich).
    $r->get('/login', [AuthController::class, 'showLogin'], auth: false);
    $r->post('/login', [AuthController::class, 'login'], auth: false);
    $r->post('/logout', [AuthController::class, 'logout']);

    // Passwort vergessen / zurücksetzen / Einladung (öffentlich).
    $r->get('/passwort-vergessen', [PasswordResetController::class, 'showForgot'], auth: false);
    $r->post('/passwort-vergessen', [PasswordResetController::class, 'sendLink'], auth: false);
    $r->get('/passwort-zuruecksetzen', [PasswordResetController::class, 'showReset'], auth: false);
    $r->post('/passwort-zuruecksetzen', [PasswordResetController::class, 'doReset'], auth: false);

    // Zwei-Faktor-Bestätigung beim Login (Zwischenschritt, öffentlich).
    $r->get('/login/2fa', [AuthController::class, 'show2fa'], auth: false);
    $r->post('/login/2fa', [AuthController::class, 'verify2fa'], auth: false);

    // Token-geschützter Cron-Endpoint (öffentlich, per Token abgesichert).
    $r->get('/cron/backup', [CronController::class, 'backup'], auth: false);
    $r->post('/cron/backup', [CronController::class, 'backup'], auth: false);

    // Web-Setup (Erstinstallation, öffentlich – greift nur ohne Benutzer).
    $r->get('/setup', [SetupController::class, 'index'], auth: false);
    $r->post('/setup', [SetupController::class, 'store'], auth: false);

    // Öffentliche Bezahlseite (Token) + Anbieter-Webhooks.
    $r->get('/zahlen/{token}', [PaymentController::class, 'pay'], auth: false);
    $r->post('/zahlen/{token}/start', [PaymentController::class, 'start'], auth: false);
    $r->get('/zahlen/{token}/erfolg', [PaymentController::class, 'success'], auth: false);
    $r->post('/webhook/stripe', [PaymentController::class, 'webhookStripe'], auth: false);

    // Dashboard.
    $r->get('/', [DashboardController::class, 'index'], cap: 'view_accounting');

    // Einstellungen – nur Admin.
    $r->get('/einstellungen', [SettingsController::class, 'edit'], cap: 'manage_settings');
    $r->post('/einstellungen', [SettingsController::class, 'update'], cap: 'manage_settings');
    $r->get('/einstellungen/rechnungen', [SettingsController::class, 'invoicing'], cap: 'manage_settings');
    $r->post('/einstellungen/rechnungen', [SettingsController::class, 'saveInvoicing'], cap: 'manage_settings');
    $r->get('/einstellungen/email', [SettingsController::class, 'emailSettings'], cap: 'manage_settings');
    $r->post('/einstellungen/email', [SettingsController::class, 'saveEmail'], cap: 'manage_settings');
    $r->get('/einstellungen/datensicherung', [SettingsController::class, 'backupSettings'], cap: 'manage_settings');
    $r->post('/einstellungen/datensicherung', [SettingsController::class, 'saveBackup'], cap: 'manage_settings');
    $r->post('/einstellungen/datensicherung/jetzt', [SettingsController::class, 'runBackup'], cap: 'manage_settings');
    $r->get('/einstellungen/datensicherung/download', [SettingsController::class, 'downloadBackup'], cap: 'manage_settings');
    $r->post('/einstellungen/datensicherung/loeschen', [SettingsController::class, 'deleteBackup'], cap: 'manage_settings');
    $r->get('/einstellungen/zahlung', [SettingsController::class, 'payments'], cap: 'manage_settings');
    $r->post('/einstellungen/zahlung', [SettingsController::class, 'savePayments'], cap: 'manage_settings');
    $r->get('/einstellungen/system', [SettingsController::class, 'system'], cap: 'manage_settings');
    $r->post('/einstellungen/update-pruefen', [UpdateController::class, 'checkNow'], cap: 'manage_settings');
    $r->post('/einstellungen/update-installieren', [UpdateController::class, 'install'], cap: 'manage_settings');

    // Benutzerverwaltung – nur Admin.
    $r->get('/benutzer', [UserController::class, 'index'], cap: 'manage_users');
    $r->get('/benutzer/neu', [UserController::class, 'create'], cap: 'manage_users');
    $r->post('/benutzer', [UserController::class, 'store'], cap: 'manage_users');
    $r->get('/benutzer/{id}/bearbeiten', [UserController::class, 'edit'], cap: 'manage_users');
    $r->post('/benutzer/{id}', [UserController::class, 'update'], cap: 'manage_users');
    $r->post('/benutzer/{id}/einladung', [UserController::class, 'resendInvite'], cap: 'manage_users');
    $r->post('/benutzer/{id}/status', [UserController::class, 'toggleActive'], cap: 'manage_users');

    // Über das Tool (jeder Angemeldete).
    $r->get('/about', [AboutController::class, 'index']);

    // Konto (eigene Zugangsdaten – jeder Angemeldete).
    $r->get('/konto', [AccountController::class, 'edit']);
    $r->post('/konto/email', [AccountController::class, 'updateEmail']);
    $r->post('/konto/passwort', [AccountController::class, 'updatePassword']);
    $r->post('/konto/2fa/start', [AccountController::class, 'start2fa']);
    $r->post('/konto/2fa/aktivieren', [AccountController::class, 'enable2fa']);
    $r->post('/konto/2fa/deaktivieren', [AccountController::class, 'disable2fa']);

    // Kundenverwaltung (Stammdaten – Vertrieb).
    $r->get('/kunden', [CustomerController::class, 'index'], cap: 'manage_sales');
    $r->get('/kunden/neu', [CustomerController::class, 'create'], cap: 'manage_sales');
    $r->post('/kunden', [CustomerController::class, 'store'], cap: 'manage_sales');
    $r->get('/kunden/{id}', [CustomerController::class, 'show'], cap: 'manage_sales');
    $r->get('/kunden/{id}/bearbeiten', [CustomerController::class, 'edit'], cap: 'manage_sales');
    $r->post('/kunden/{id}', [CustomerController::class, 'update'], cap: 'manage_sales');
    $r->post('/kunden/{id}/loeschen', [CustomerController::class, 'destroy'], cap: 'manage_sales');
    $r->post('/kunden/{id}/archivieren', [CustomerController::class, 'archive'], cap: 'manage_sales');
    $r->post('/kunden/{id}/wiederherstellen', [CustomerController::class, 'unarchive'], cap: 'manage_sales');

    // Lieferanten & Dienstleister (Stammdaten – Ausgaben).
    $r->get('/lieferanten', [VendorController::class, 'index'], cap: 'manage_expenses');
    $r->get('/lieferanten/neu', [VendorController::class, 'create'], cap: 'manage_expenses');
    $r->post('/lieferanten', [VendorController::class, 'store'], cap: 'manage_expenses');
    $r->get('/lieferanten/{id}', [VendorController::class, 'show'], cap: 'manage_expenses');
    $r->get('/lieferanten/{id}/bearbeiten', [VendorController::class, 'edit'], cap: 'manage_expenses');
    $r->post('/lieferanten/{id}', [VendorController::class, 'update'], cap: 'manage_expenses');
    $r->post('/lieferanten/{id}/loeschen', [VendorController::class, 'destroy'], cap: 'manage_expenses');
    $r->post('/lieferanten/{id}/archivieren', [VendorController::class, 'archive'], cap: 'manage_expenses');
    $r->post('/lieferanten/{id}/wiederherstellen', [VendorController::class, 'unarchive'], cap: 'manage_expenses');

    // Projekte (Vertrieb).
    $r->get('/projekte', [ProjectController::class, 'index'], cap: 'manage_sales');
    $r->get('/projekte/neu', [ProjectController::class, 'create'], cap: 'manage_sales');
    $r->post('/projekte', [ProjectController::class, 'store'], cap: 'manage_sales');
    $r->get('/projekte/{id}', [ProjectController::class, 'show'], cap: 'manage_sales');
    $r->get('/projekte/{id}/bearbeiten', [ProjectController::class, 'edit'], cap: 'manage_sales');
    $r->post('/projekte/{id}', [ProjectController::class, 'update'], cap: 'manage_sales');
    $r->post('/projekte/{id}/loeschen', [ProjectController::class, 'destroy'], cap: 'manage_sales');
    $r->post('/projekte/{id}/leistungen', [ProjectController::class, 'addItem'], cap: 'manage_sales');
    $r->post('/projekte/{id}/leistungen/{itemId}/loeschen', [ProjectController::class, 'deleteItem'], cap: 'manage_sales');
    $r->post('/projekte/{id}/angebot', [ProjectController::class, 'createQuote'], cap: 'manage_sales');
    $r->post('/projekte/{id}/rechnung', [ProjectController::class, 'createInvoice'], cap: 'manage_sales');

    // Leistungskatalog (Vertrieb).
    $r->get('/katalog', [CatalogController::class, 'index'], cap: 'manage_sales');
    $r->get('/katalog/neu', [CatalogController::class, 'create'], cap: 'manage_sales');
    $r->post('/katalog', [CatalogController::class, 'store'], cap: 'manage_sales');
    $r->get('/katalog/{id}/bearbeiten', [CatalogController::class, 'edit'], cap: 'manage_sales');
    $r->post('/katalog/{id}', [CatalogController::class, 'update'], cap: 'manage_sales');
    $r->post('/katalog/{id}/loeschen', [CatalogController::class, 'destroy'], cap: 'manage_sales');

    // Angebote (Vertrieb).
    $r->get('/angebote', [QuoteController::class, 'index'], cap: 'manage_sales');
    $r->get('/angebote/neu', [QuoteController::class, 'create'], cap: 'manage_sales');
    $r->post('/angebote', [QuoteController::class, 'store'], cap: 'manage_sales');
    $r->get('/angebote/{id}', [QuoteController::class, 'show'], cap: 'manage_sales');
    $r->get('/angebote/{id}/bearbeiten', [QuoteController::class, 'edit'], cap: 'manage_sales');
    $r->post('/angebote/{id}', [QuoteController::class, 'update'], cap: 'manage_sales');
    $r->post('/angebote/{id}/status', [QuoteController::class, 'changeStatus'], cap: 'manage_sales');
    $r->post('/angebote/{id}/loeschen', [QuoteController::class, 'destroy'], cap: 'manage_sales');
    $r->get('/angebote/{id}/pdf', [QuoteController::class, 'pdf'], cap: 'manage_sales');
    $r->post('/angebote/{id}/senden', [QuoteController::class, 'send'], cap: 'manage_sales');
    $r->post('/angebote/{id}/in-rechnung', [QuoteController::class, 'convertToInvoice'], cap: 'manage_sales');

    // Wiederkehrende Rechnungen (Vertrieb).
    $r->get('/wiederkehrend', [RecurringController::class, 'index'], cap: 'manage_sales');
    $r->get('/wiederkehrend/neu', [RecurringController::class, 'create'], cap: 'manage_sales');
    $r->post('/wiederkehrend', [RecurringController::class, 'store'], cap: 'manage_sales');
    $r->get('/wiederkehrend/{id}/bearbeiten', [RecurringController::class, 'edit'], cap: 'manage_sales');
    $r->post('/wiederkehrend/{id}', [RecurringController::class, 'update'], cap: 'manage_sales');
    $r->post('/wiederkehrend/{id}/loeschen', [RecurringController::class, 'destroy'], cap: 'manage_sales');

    // Rechnungen – ansehen (Buchhaltung), ändern (Vertrieb).
    $r->get('/rechnungen', [InvoiceController::class, 'index'], cap: 'view_accounting');
    $r->get('/rechnungen/neu', [InvoiceController::class, 'create'], cap: 'manage_sales');
    $r->post('/rechnungen', [InvoiceController::class, 'store'], cap: 'manage_sales');
    $r->get('/rechnungen/{id}', [InvoiceController::class, 'show'], cap: 'view_accounting');
    $r->get('/rechnungen/{id}/bearbeiten', [InvoiceController::class, 'edit'], cap: 'manage_sales');
    $r->post('/rechnungen/{id}', [InvoiceController::class, 'update'], cap: 'manage_sales');
    $r->post('/rechnungen/{id}/finalisieren', [InvoiceController::class, 'finalize'], cap: 'manage_sales');
    $r->post('/rechnungen/{id}/storno', [InvoiceController::class, 'cancel'], cap: 'manage_sales');
    $r->post('/rechnungen/{id}/loeschen', [InvoiceController::class, 'destroy'], cap: 'manage_sales');
    $r->get('/rechnungen/{id}/pdf', [InvoiceController::class, 'pdf'], cap: 'view_accounting');
    $r->post('/rechnungen/{id}/zahlung', [InvoiceController::class, 'addPayment'], cap: 'manage_sales');
    $r->post('/rechnungen/{id}/senden', [InvoiceController::class, 'send'], cap: 'manage_sales');
    $r->get('/rechnungen/{id}/xrechnung', [InvoiceController::class, 'xrechnung'], cap: 'view_accounting');

    // Einnahmen – ansehen (Buchhaltung), ändern (Vertrieb).
    $r->get('/einnahmen', [IncomeController::class, 'index'], cap: 'view_accounting');
    $r->get('/einnahmen/neu', [IncomeController::class, 'create'], cap: 'manage_sales');
    $r->post('/einnahmen', [IncomeController::class, 'store'], cap: 'manage_sales');
    $r->get('/einnahmen/{id}', [IncomeController::class, 'show'], cap: 'view_accounting');
    $r->get('/einnahmen/{id}/bearbeiten', [IncomeController::class, 'edit'], cap: 'manage_sales');
    $r->post('/einnahmen/{id}', [IncomeController::class, 'update'], cap: 'manage_sales');
    $r->post('/einnahmen/{id}/loeschen', [IncomeController::class, 'destroy'], cap: 'manage_sales');

    // Ausgaben – ansehen (Buchhaltung), ändern (Ausgaben).
    $r->get('/ausgaben', [ExpenseController::class, 'index'], cap: 'view_accounting');
    $r->get('/ausgaben/neu', [ExpenseController::class, 'create'], cap: 'manage_expenses');
    $r->post('/ausgaben', [ExpenseController::class, 'store'], cap: 'manage_expenses');
    $r->get('/ausgaben/{id}', [ExpenseController::class, 'show'], cap: 'view_accounting');
    $r->get('/ausgaben/{id}/bearbeiten', [ExpenseController::class, 'edit'], cap: 'manage_expenses');
    $r->post('/ausgaben/{id}', [ExpenseController::class, 'update'], cap: 'manage_expenses');
    $r->post('/ausgaben/{id}/loeschen', [ExpenseController::class, 'destroy'], cap: 'manage_expenses');

    // Wiederkehrende Ausgaben / Daueraufwendungen (Ausgaben).
    $r->get('/dauerausgaben', [RecurringExpenseController::class, 'index'], cap: 'manage_expenses');
    $r->get('/dauerausgaben/neu', [RecurringExpenseController::class, 'create'], cap: 'manage_expenses');
    $r->post('/dauerausgaben', [RecurringExpenseController::class, 'store'], cap: 'manage_expenses');
    $r->get('/dauerausgaben/{id}/bearbeiten', [RecurringExpenseController::class, 'edit'], cap: 'manage_expenses');
    $r->post('/dauerausgaben/{id}', [RecurringExpenseController::class, 'update'], cap: 'manage_expenses');
    $r->post('/dauerausgaben/{id}/loeschen', [RecurringExpenseController::class, 'destroy'], cap: 'manage_expenses');

    // Belege – ansehen/herunterladen (Buchhaltung), ändern (Ausgaben).
    $r->get('/belege', [ReceiptController::class, 'index'], cap: 'view_accounting');
    $r->get('/belege/neu', [ReceiptController::class, 'create'], cap: 'manage_expenses');
    $r->post('/belege', [ReceiptController::class, 'store'], cap: 'manage_expenses');
    $r->get('/belege/{id}/download', [ReceiptController::class, 'download'], cap: 'view_accounting');
    $r->post('/belege/{id}/zuordnen', [ReceiptController::class, 'link'], cap: 'manage_expenses');
    $r->post('/belege/{id}/loeschen', [ReceiptController::class, 'destroy'], cap: 'manage_expenses');

    // Buchungsjournal.
    $r->get('/buchhaltung', [LedgerController::class, 'index'], cap: 'view_accounting');

    // EÜR-Auswertungen.
    $r->get('/auswertungen', [ReportController::class, 'euer'], cap: 'view_accounting');
    $r->get('/auswertungen/csv', [ReportController::class, 'euerCsv'], cap: 'export');
    $r->get('/auswertungen/pdf', [ReportController::class, 'euerPdf'], cap: 'view_accounting');

    // Mahnwesen (Vertrieb).
    $r->get('/mahnungen', [ReminderController::class, 'index'], cap: 'manage_sales');
    $r->post('/mahnungen', [ReminderController::class, 'store'], cap: 'manage_sales');
    $r->get('/mahnungen/{id}/pdf', [ReminderController::class, 'pdf'], cap: 'manage_sales');
    $r->post('/mahnungen/{id}/senden', [ReminderController::class, 'send'], cap: 'manage_sales');

    // Exporte.
    $r->get('/exporte', [ExportController::class, 'index'], cap: 'export');
    $r->get('/exporte/einnahmen', [ExportController::class, 'incomeCsv'], cap: 'export');
    $r->get('/exporte/ausgaben', [ExportController::class, 'expensesCsv'], cap: 'export');
    $r->get('/exporte/journal', [ExportController::class, 'journalCsv'], cap: 'export');
    $r->get('/exporte/datev', [ExportController::class, 'datevCsv'], cap: 'export');
    $r->get('/exporte/jahr', [ExportController::class, 'yearZip'], cap: 'export');
    $r->get('/exporte/belege', [ExportController::class, 'receiptsZip'], cap: 'export');

    // Änderungsprotokoll (Audit-Log).
    $r->get('/protokoll', [AuditController::class, 'index'], cap: 'view_accounting');

    // CSV-Bankimport (Ausgaben/Finanzen).
    $r->get('/bankimport', [BankImportController::class, 'index'], cap: 'manage_expenses');
    $r->post('/bankimport/vorschau', [BankImportController::class, 'preview'], cap: 'manage_expenses');
    $r->post('/bankimport/buchen', [BankImportController::class, 'commit'], cap: 'manage_expenses');

    // KI-Assistent (optional).
    $r->get('/assistent', [AssistantController::class, 'index'], cap: 'use_assistant');
    $r->post('/assistent', [AssistantController::class, 'ask'], cap: 'use_assistant');
};
