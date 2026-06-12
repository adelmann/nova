# Nova <small>v0.9.25</small>

Webbasiertes Business- & Buchhaltungstool für ein deutsches Kleingewerbe –
Kunden, Angebote, Rechnungen, Ausgaben, Belege und Einnahmen‑Überschuss‑Rechnung
(EÜR). **Reines PHP, SQLite, ohne Node und ohne Build‑Schritt** – deploybar auf
einfachem Shared Hosting. Optional als **PWA** auf dem Handy installierbar.

> Nova ersetzt keine Steuerberatung. Nutzung auf eigenes Risiko, ohne
> Gewährleistung (siehe [LICENSE](LICENSE) und Haftungsausschluss im Setup).

Projektseite & Anleitung: **https://adelmann.github.io/nova/**

## Funktionsumfang

- **Stammdaten:** Kunden, Projekte (mit abrechenbaren Leistungen), Lieferanten &
  Dienstleister, Leistungskatalog – jeweils mit Archivierung.
- **Verkauf:** Angebote → per Klick in Rechnung umwandeln; Rechnungen mit
  lückenlosen Nummern, Finalisierung/Sperre (GoBD), Storno, Zahlungserfassung,
  Mahnwesen (mehrstufig, PDF + E‑Mail). **Abschlags‑ und Schlussrechnungen**
  (Schlussrechnung verrechnet geleistete Abschläge), **Rabatt** (Prozent/Betrag)
  und **Skonto**‑Konditionen, **wiederkehrende Rechnungen**.
- **Online‑Zahlung (optional):** Bezahllink per **Stripe** und **PayPal**; der
  Bruttobetrag wird als Einnahme, die Anbietergebühr automatisch als Ausgabe
  gebucht (EÜR), idempotent.
- **Ausgaben & Belege:** Ausgaben mit EÜR‑Kategorie, Beleg‑Upload (Foto **oder
  PDF**), Belege frei zuordnen oder direkt verbuchen, **wiederkehrende Ausgaben**
  (Daueraufwendungen). **CSV‑Bankimport** mit Format‑Vorlagen, speicherbaren
  Mapping‑Profilen und automatischem Zahlungsabgleich.
- **Anlagevermögen & AfA:** Anlagegüter mit linearer Abschreibung (pro rata
  temporis) oder GWG‑Sofortabschreibung; jährliche AfA wird automatisch gebucht.
- **Buchhaltung:** automatisches, unveränderbares Buchungsjournal, EÜR‑Auswertung
  (Monat/Kategorie + Einzelaufstellung), CSV/PDF‑Export, **DATEV‑Buchungsstapel**,
  Jahres‑ZIP. **Offene‑Posten‑Liste** mit Fälligkeits‑Aging und **Kunden‑Kontoauszug**.
- **Mahnwesen:** mehrstufig mit konfigurierbarer **Mahngebühr** und
  **Verzugszinsen**; optional automatische Zahlungserinnerungen.
- **E‑Rechnung:** **XRechnung‑3.0‑XML** (UBL) und **ZUGFeRD / Factur‑X**
  (EN 16931, CII‑XML eingebettet in PDF/A‑3).
- **Dashboard:** Umsatz/Ausgaben/Gewinn, Liquiditätsvorschau, anteilige
  Zahlungsmethoden, Top‑Kunden, Ausgaben nach Kategorie, ⌀ Zahldauer,
  Kleinunternehmer‑Umsatzgrenze.
- **PDF‑Erzeugung** für Angebote, Rechnungen, Mahnungen, EÜR – mit Logo & Briefkopf.
- **E‑Mail‑Versand** (SMTP oder `mail()`), inkl. Signatur und Textvorlagen mit
  Platzhaltern.
- **KI‑Assistent** (optional, Anthropic‑API).
- **Mehrbenutzer & Rollen**, **2FA (TOTP)**, **Passwort‑Reset**, **Backups**
  (verschlüsselt, konfigurierbare Häufigkeit) und **Self‑Update** über GitHub.

## Anforderungen

- **PHP 8.1+** mit den Extensions: `pdo_sqlite`, `mbstring`, `dom`, `gd`, `zip`, `curl`
- Webserver mit `mod_rewrite` (Apache); Docroot zeigt auf `public/`
- Für die produktive PWA‑Installation: **HTTPS**

## Installation

### Auf Shared Hosting (empfohlen)

1. Alle Dateien **inklusive `vendor/`** in den Webspace hochladen (kein
   `composer install` nötig). Nicht nötig sind `tmp/`, `.git/`, `docker-*`.
2. Den Docroot der (Sub‑)Domain auf **`public/`** zeigen lassen. Geht das nicht,
   alles in den Docroot legen – die `.htaccess` im Wurzelverzeichnis leitet nach
   `public/` um und sperrt `src/`, `storage/`, `vendor/` usw.
3. Sicherstellen, dass **`storage/` beschreibbar** ist (`chmod -R 775 storage`).
4. Die Domain im Browser öffnen → der **Setup‑Assistent** (`/setup`) legt das
   Datenbankschema an, erstellt den Admin‑Zugang und erfasst die Firmen‑Basisdaten.

> Alternativ per CLI statt Web‑Setup: `php bin/migrate.php` und
> `php bin/seed-admin.php deine@mail.de "Passwort" "Name"`.

### Lokale Entwicklung (Docker)

```bash
docker compose up --build      # migriert + legt Admin an, läuft auf :8000
```

### Lokale Entwicklung (natives PHP)

```bash
php bin/migrate.php
php -S localhost:8000 -t public public/index.php
```

## Projektstruktur

| Pfad | Zweck |
|------|-------|
| `public/` | Web‑Root (Front‑Controller, Assets, Manifest, Service Worker) |
| `src/Core/` | Router, DB, Auth/ACL, View, Session, CSRF, Version |
| `src/Controllers/` · `src/Models/` · `src/Services/` | Logik je Modul |
| `views/` | PHP‑Templates |
| `migrations/` | Versionierte SQL‑Migrationen |
| `storage/` | SQLite‑DB, Belege, PDFs, Backups, Cache (nicht im Web‑Root, nicht versioniert) |
| `bin/` | CLI‑Skripte (s.u.) |

### CLI‑Skripte

| Skript | Zweck |
|--------|-------|
| `bin/migrate.php` | Ausstehende DB‑Migrationen anwenden |
| `bin/seed-admin.php` | Admin‑Benutzer anlegen |
| `bin/backup.php` | Backup erstellen + verteilen (für CLI‑Cron) |
| `bin/sweep.php` | Wartung: überfällige Rechnungen, wiederkehrende Rechnungen/Ausgaben, AfA‑Buchungen, Auto‑Mahnungen, Update‑Check |
| `bin/reset-password.php` | Passwort eines Benutzers per CLI zurücksetzen (Notfall) |

## Benutzer & Rollen

Verwaltung unter **Benutzer** (nur Admin). Neue Konten erhalten einen
**Einladungslink** per E‑Mail und setzen ihr Passwort selbst. Benutzer werden
**deaktiviert statt gelöscht** (das Audit‑Log bleibt intakt).

| Rolle | Rechte |
|-------|--------|
| **Inhaber (admin)** | Vollzugriff inkl. Einstellungen & Benutzer |
| **Mitarbeiter (staff)** | operatives Arbeiten, keine Einstellungen/Benutzer |
| **Steuerberater (accountant)** | **nur lesend**: Buchhaltungs-/Finanzansichten + Exporte |

**Zwei‑Faktor‑Authentifizierung (TOTP)** ist pro Benutzer optional aktivierbar
(Konto → 2FA), kompatibel mit Google Authenticator/Authy u. a., inkl.
Recovery‑Codes.

## Datensicherung

Unter **Einstellungen → Datensicherung**:

- Erstellt ein <abbr title="sofern der Server ZIP‑Verschlüsselung unterstützt">AES‑256‑verschlüsseltes</abbr>
  ZIP aus Datenbank + Uploads; optional zusätzlich per E‑Mail und/oder in ein
  Serververzeichnis.
- **Backups auflisten, herunterladen und löschen** direkt im Tool; „Backup jetzt
  erstellen" auf Knopfdruck. Es werden die letzten 14 aufbewahrt.
- **Konfigurierbare Häufigkeit:** Mindestabstand zwischen Backups und ein
  separates Intervall für den E‑Mail‑Versand – ein stündlicher Cron legt damit
  z. B. nur ein Backup pro Tag an.
- **Automatisch per Cron** – entweder CLI (`php bin/backup.php`) oder der
  **token‑geschützte Web‑Cron** (URL wird in den Einstellungen angezeigt), z. B.:
  `wget -qO- "https://deine-domain.de/cron/backup?token=…"`

Für die übrigen Wartungsaufgaben (wiederkehrende Rechnungen/Ausgaben, AfA,
Mahnungen, Überfälligkeit) gibt es analog `php bin/sweep.php` bzw. den Web‑Cron
`…/cron/sweep?token=…`. Beide URLs samt fertiger Crontab‑Beispiele werden in
**Einstellungen → Datensicherung** angezeigt. Alle Aufgaben sind idempotent.

## Updates

Nova prüft GitHub (Repository über `NOVA_GITHUB_REPO`, Standard `adelmann/nova`)
auf neue Releases. Unter **Einstellungen → System** lässt sich ein Update **per
Klick installieren**: erst Backup, dann Download/Entpacken des Release‑ZIP, dann
DB‑Migrationen. `storage/`, `config.php` und die Datenbank bleiben dabei
unangetastet.

> Ein installierbares Release‑ZIP muss die **komplette Anwendung inkl. `vendor/`**
> enthalten (da auf Shared Hosting kein Composer läuft).

## Konfiguration (`config.php` / Umgebungsvariablen)

| Variable | Zweck |
|----------|-------|
| `NOVA_APP_URL` | Basis‑URL (für E‑Mail‑Links & Cron‑URL) |
| `NOVA_DB_PATH` | Pfad zur SQLite‑Datei |
| `NOVA_GITHUB_REPO` | Repository für die Update‑Prüfung |
| `ANTHROPIC_API_KEY` | aktiviert den KI‑Assistenten (optional) |

## PWA

Nova ist als Progressive Web App installierbar (Manifest + Service Worker). Auf
dem Handy über „Zum Startbildschirm hinzufügen" (iOS Safari) bzw. „App
installieren" (Android Chrome). Es werden nur statische Assets gecacht – niemals
Seiten, PDFs oder Daten.

## Lizenz

[MIT](LICENSE) © 2026 Adelmann Solutions. Bereitgestellt „wie besehen", ohne
Gewährleistung; Nutzung auf eigenes Risiko.
