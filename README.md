# WP User Rights

**Autor:** Friederich Loheide
**Website:** [loheide.eu](https://loheide.eu)
**Version:** 1.0.0
**Erfordert WordPress:** 5.8+
**Erfordert PHP:** 7.4+
**Lizenz:** GPL-2.0-or-later

---

## Beschreibung

WP User Rights ermöglicht die granulare Steuerung von Backend-Zugriffsrechten pro Benutzerrolle. Administratoren sehen weiterhin alles — alle anderen Rollen sehen standardmäßig nur das Dashboard und erhalten dann gezielt Zugriff auf einzelne Menüpunkte.

Das Plugin liest alle im WordPress-Backend registrierten Menüpunkte dynamisch aus — inklusive der Menüs von anderen installierten Plugins — und zeigt sie in einer übersichtlichen Baumstruktur zur Konfiguration an.

---

## Funktionen

- **Menü-Zugriffskontrolle** — Legt pro Rolle fest, welche Top-Level- und Untermenüpunkte sichtbar sind
- **Automatische Capability-Vergabe** — Beim Speichern werden die benötigten WordPress-Capabilities der Rolle automatisch hinzugefügt und beim Entziehen von Rechten wieder entfernt
- **Direktzugriff-Schutz** — Blockiert den direkten URL-Aufruf von nicht erlaubten Seiten, nicht nur die Menü-Sichtbarkeit
- **Inhaltsfilter** — Schränkt die Admin-Listen für Beiträge (nach Kategorie) und Seiten (nach Slug) ein
- **Gutenberg / REST API** — Die Inhaltsfilter gelten auch für REST-API-Anfragen des Block-Editors
- **Multi-Rollen-Unterstützung** — Hat ein Benutzer mehrere Rollen, erhält er die Vereinigung aller Rechte
- **Suchfunktion** — Menüpunkte in der Einstellungsseite durchsuchbar
- **Capability-Vorschau** — Zeigt live an, welche WordPress-Capabilities die aktuelle Auswahl verleiht
- **Saubere Deinstallation** — Entfernt alle Optionen und vergebenen Capabilities beim Löschen des Plugins

---

## Installation

1. Den Ordner `wp-userrights` in das Verzeichnis `wp-content/plugins/` kopieren
2. Im WordPress-Backend unter **Plugins** das Plugin **WP User Rights** aktivieren
3. Im Menü erscheint der neue Punkt **Benutzerrechte**

---

## Verwendung

### Schritt 1 — Rolle wählen
Auf der Einstellungsseite (**Benutzerrechte**) im Dropdown die gewünschte Rolle auswählen.

### Schritt 2 — Menüpunkte freigeben
Die Checkboxen der erlaubten Menüpunkte aktivieren. Die Baumstruktur zeigt Top-Level-Einträge und deren Untermenüs. Wird ein Untermenüpunkt aktiviert, wird der übergeordnete Eintrag automatisch mitgesetzt.

Die **Capability-Vorschau** unter der Toolbar zeigt in Echtzeit, welche WordPress-Capabilities beim Speichern an die Rolle vergeben werden.

### Schritt 3 — Inhaltsfilter (optional)
Unterhalb der Menü-Checkboxen können optional Inhaltsfilter konfiguriert werden:

| Feld | Beschreibung |
|---|---|
| **Nur Beiträge in Kategorien** | Kommagetrennte Kategorie-Slugs. Die Rolle sieht nur Beiträge in diesen Kategorien. |
| **Nur Seiten mit diesen Slugs** | Kommagetrennte Seiten-Slugs. Die Rolle sieht nur diese Seiten in der Seitenliste. |

Leer lassen = keine Einschränkung (sofern der Menüzugriff besteht).

### Schritt 4 — Speichern
Auf **Einstellungen speichern** klicken. Das Plugin vergibt die benötigten Capabilities automatisch an die Rolle.

---

## Beispiele

### Rolle „MAV"
Die Rolle soll Beiträge in der Kategorie „MAV" schreiben und die Unterseite „MAV" unter Seiten bearbeiten können.

**Menü-Checkboxen aktivieren:**
- Beiträge (`edit.php`)
- Neuen Beitrag erstellen (`post-new.php`)
- Seiten (`edit.php?post_type=page`) — wird automatisch gesetzt wenn Unterseite gewählt

**Inhaltsfilter:**
- Nur Beiträge in Kategorien: `mav`
- Nur Seiten mit diesen Slugs: `mav`

### Rolle „Künstlerteam"
Die Rolle soll nur das eigene Plugin „Künstlerteam" und die gleichnamige Seite sehen.

**Menü-Checkboxen aktivieren:**
- Künstlerteam *(Plugin-Menüeintrag)*
- Seiten (`edit.php?post_type=page`)

**Inhaltsfilter:**
- Nur Seiten mit diesen Slugs: `kuenstlerteam`

---

## Hinweise

### WordPress-Standardrollen
Die Plugin-Einstellungsseite zeigt eine Warnung wenn eine eingebaute WordPress-Rolle (Editor, Autor, Mitarbeiter, Abonnent) bearbeitet wird. Diese Rollen haben bereits vordefinierte Capabilities — Änderungen können deren normales Verhalten beeinflussen. Es wird empfohlen, für diesen Zweck **eigene benutzerdefinierte Rollen** zu erstellen (z. B. mit einem Role-Manager-Plugin wie *Members* oder *User Role Editor*).

### Capability-Verwaltung
Das Plugin merkt sich welche Capabilities es einer Rolle hinzugefügt hat (`managed_caps`). Beim Entziehen von Menürechten werden nur diese selbst verwalteten Capabilities wieder entfernt — Capabilities die die Rolle bereits zuvor hatte, bleiben erhalten.

### Direktzugriff-Schutz
Das Entfernen von Menüpunkten ist nicht nur optisch: Das Plugin prüft bei jedem Backend-Seitenaufruf (`admin_init`) ob die Seite für die Rolle erlaubt ist und leitet andernfalls zum Dashboard um. Dabei werden auch `/wp-admin/post.php` (Beitrag/Seite bearbeiten) und Plugin-Seiten (`admin.php?page=...`) korrekt behandelt.

### Deinstallation
Beim **Löschen** des Plugins (nicht nur Deaktivieren) werden automatisch:
- Die Option `wp_userrights_permissions` aus der Datenbank entfernt
- Alle durch das Plugin vergebenen Capabilities von den Rollen zurückgenommen

---

## Technische Details

| Datei | Funktion |
|---|---|
| `wp-userrights.php` | Bootstrap, Konstanten, Hook-Registrierung |
| `includes/class-admin-menu.php` | Menü-Enforcement (Priorität 9999), Direktzugriff-Schutz |
| `includes/class-settings.php` | Einstellungsseite, Capability-Synchronisierung |
| `includes/class-content-filter.php` | `pre_get_posts`-Filter für Admin & REST API |
| `uninstall.php` | Bereinigung bei Plugin-Löschung |
| `assets/admin.css` | Styles der Einstellungsseite |
| `assets/admin.js` | Interaktivität: Suche, Zähler, Capability-Vorschau |

**WordPress-Option:** `wp_userrights_permissions`

Gespeichertes Format:
```php
[
    'mav' => [
        'menu_slugs'         => ['edit.php', 'post-new.php', 'edit.php?post_type=page'],
        'allowed_categories' => ['mav'],
        'allowed_page_slugs' => ['mav'],
        'managed_caps'       => ['read', 'edit_posts', 'edit_pages'],
    ],
]
```

---

## Changelog

### 1.0.0
- Erstveröffentlichung
- Menü-Zugriffskontrolle pro Rolle
- Automatische Capability-Vergabe und -Entzug
- Direktzugriff-Schutz via `admin_init`
- Inhaltsfilter für Beiträge und Seiten (Admin + REST API)
- Multi-Rollen-Union
- Capability-Vorschau in Echtzeit
- Suchfilter für Menüpunkte
- Warnung für WordPress-Standardrollen
- Saubere Deinstallationsroutine

---

*Entwickelt von [Friederich Loheide](https://loheide.eu)*
