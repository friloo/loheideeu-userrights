# LOHEIDE.EU - User Rights

**Autor:** Friederich Loheide
**Website:** [loheide.eu](https://loheide.eu)
**Version:** 1.2.0
**Erfordert WordPress:** 5.8+
**Erfordert PHP:** 7.4+
**Lizenz:** MIT — siehe [LICENSE](LICENSE)

---

## Beschreibung

WP User Rights ermöglicht die granulare Steuerung von Backend-Zugriffsrechten pro Benutzerrolle. Administratoren sehen weiterhin alles — alle anderen Rollen sehen standardmäßig nur das Dashboard und erhalten dann gezielt Zugriff auf einzelne Menüpunkte.

Das Plugin liest alle im WordPress-Backend registrierten Menüpunkte dynamisch aus — inklusive der Menüs von anderen installierten Plugins — und zeigt sie in einer übersichtlichen Baumstruktur zur Konfiguration an.

---

## Funktionen

- **Menü-Zugriffskontrolle** — Legt pro Rolle fest, welche Top-Level- und Untermenüpunkte sichtbar sind
- **Automatische Capability-Vergabe** — Beim Speichern werden die benötigten WordPress-Capabilities der Rolle automatisch hinzugefügt und beim Entziehen von Rechten wieder entfernt
- **Direktzugriff-Schutz** — Blockiert den direkten URL-Aufruf von nicht erlaubten Seiten, nicht nur die Menü-Sichtbarkeit
- **Login-Weiterleitung** — Nach dem Login werden Benutzer direkt zur ersten erlaubten Seite weitergeleitet
- **Inhaltsfilter** — Schränkt die Admin-Listen für Beiträge (nach Kategorie) und Seiten (nach Slug) ein
- **Gutenberg / REST API** — Die Inhaltsfilter gelten auch für REST-API-Anfragen des Block-Editors
- **Multi-Rollen-Unterstützung** — Hat ein Benutzer mehrere Rollen, erhält er die Vereinigung aller Rechte
- **Eigene Rollen erstellen** — Neue Benutzerrollen direkt im Plugin anlegen und löschen
- **Benutzerverwaltung** — Eigene Rollen per Chip-UI additiv zu Benutzern zuweisen (AJAX, ohne Seitenneuladen)
- **Admin-Bar-Filter** — Entfernt Einträge in der Admin-Bar, auf die die Rolle keinen Zugriff hat
- **Abonnenten-Sperre** — Benutzer mit ausschließlich der Abonnenten-Rolle werden vollständig aus dem Backend ausgesperrt
- **Suchfunktion** — Menüpunkte und Benutzer in der Einstellungsseite durchsuchbar
- **Paginierung** — Benutzerliste wird seitenweise angezeigt (20 pro Seite)
- **Capability-Vorschau** — Zeigt live an, welche WordPress-Capabilities die aktuelle Auswahl verleiht
- **Saubere Deinstallation** — Entfernt alle Optionen, vergebenen Capabilities und plugin-erstellte Rollen beim Löschen des Plugins

---

## Installation

1. Den Ordner `wp-userrights` in das Verzeichnis `wp-content/plugins/` kopieren
2. Im WordPress-Backend unter **Plugins** das Plugin **WP User Rights** aktivieren
3. Im Menü erscheint der neue Punkt **Benutzerrechte**

---

## Verwendung

Die Einstellungsseite ist in drei Tabs gegliedert.

---

### Tab „Berechtigungen"

Hier werden die Menürechte pro Rolle konfiguriert.

#### Schritt 1 — Rolle wählen
Im Dropdown die gewünschte Rolle auswählen. Eigene (plugin-erstellte) Rollen sind mit einem grünen Badge gekennzeichnet, WordPress-Standardrollen mit einem orangenen Warnhinweis.

#### Schritt 2 — Menüpunkte freigeben
Die Checkboxen der erlaubten Menüpunkte aktivieren. Die Baumstruktur zeigt Top-Level-Einträge und deren Untermenüs. Wird ein Untermenüpunkt aktiviert, wird der übergeordnete Eintrag automatisch mitgesetzt.

Die **Capability-Vorschau** unter der Toolbar zeigt in Echtzeit, welche WordPress-Capabilities beim Speichern an die Rolle vergeben werden.

#### Schritt 3 — Inhaltsfilter (optional)
Unterhalb der Menü-Checkboxen können optional Inhaltsfilter konfiguriert werden:

| Feld | Beschreibung |
|---|---|
| **Nur Beiträge in Kategorien** | Kommagetrennte Kategorie-Slugs. Die Rolle sieht nur Beiträge in diesen Kategorien. |
| **Nur Seiten mit diesen Slugs** | Kommagetrennte Seiten-Slugs. Die Rolle sieht nur diese Seiten in der Seitenliste. |
| **Mediathek einschränken** | Nur eigene hochgeladene Medien werden angezeigt. |

Leer lassen = keine Einschränkung (sofern der Menüzugriff besteht).

#### Schritt 4 — Speichern
Auf **Einstellungen speichern** klicken. Das Plugin vergibt die benötigten Capabilities automatisch an die Rolle.

---

### Tab „Rollen verwalten"

Hier können neue Benutzerrollen für WordPress erstellt werden — direkt im Plugin, ohne weiteres Plugin.

#### Neue Rolle erstellen
1. **Rollenname** eingeben (z. B. „Team 1") — der Slug wird automatisch generiert
2. Den generierten **Rollen-Slug** prüfen und ggf. anpassen
3. Auf **Rolle erstellen** klicken

Die neue Rolle erscheint sofort in der Rollenliste unterhalb und ist in allen anderen Tabs verfügbar.

#### Rollen löschen
Nur vom Plugin erstellte Rollen können gelöscht werden. Beim Löschen:
- Wird die Rolle von allen betroffenen Benutzern entfernt
- Werden die Rollendefinition und alle gespeicherten Berechtigungen gelöscht

---

### Tab „Benutzer"

Hier können Benutzern zusätzlich zu ihrer Basis-Rolle eigene plugin-verwaltete Rollen zugewiesen werden.

#### Rollenübersicht
Die Tabelle zeigt alle Benutzer (außer Administratoren) mit:
- **Avatar und Name/E-Mail**
- **Basis-Rolle** — die ursprüngliche WordPress-Rolle des Benutzers
- **Plugin-Rollen** — zugewiesene Rollen erscheinen als Chips; über den **+**-Button können weitere Rollen aus einem Dropdown ergänzt werden

Die Zuweisung ist **additiv**: Der Benutzer behält seine Basis-Rolle und erhält zusätzlich die gewählte Rolle. Er erhält damit die Vereinigung aller Rechte beider Rollen.

#### Suche
Über das Suchfeld können Benutzer nach Name, Anzeigename oder E-Mail-Adresse gefiltert werden. Die Suche löst nach kurzer Tipp-Pause automatisch aus.

#### Paginierung
Bei mehr als 20 Benutzern erscheinen Seitennavigation und eine Anzeige „X–Y von Z Benutzern". Die Suche berücksichtigt alle Seiten.

---

## Beispiele

### Rolle „Team 1"
Die Rolle soll Beiträge in der Kategorie „team1" schreiben und eine bestimmte Seite bearbeiten können.

**Schritt 1 — Rolle erstellen (Tab „Rollen verwalten"):**
- Rollenname: `Team 1`, Slug: `team1` → Rolle erstellen

**Schritt 2 — Berechtigungen setzen (Tab „Berechtigungen"):**

Menü-Checkboxen aktivieren:
- Beiträge (`edit.php`)
- Neuen Beitrag erstellen (`post-new.php`)
- Seiten (`edit.php?post_type=page`)

Inhaltsfilter:
- Nur Beiträge in Kategorien: `team1`
- Nur Seiten mit diesen Slugs: `team1`

**Schritt 3 — Benutzer zuweisen (Tab „Benutzer"):**
Den gewünschten Benutzer suchen und über den **+**-Button die Rolle „Team 1" als Chip hinzufügen.

### Rolle „Team 2"
Die Rolle soll nur einen bestimmten Plugin-Menüeintrag und eine eigene Seite sehen.

**Menü-Checkboxen aktivieren:**
- Gewünschter Plugin-Menüeintrag
- Seiten (`edit.php?post_type=page`)

**Inhaltsfilter:**
- Nur Seiten mit diesen Slugs: `team2`

---

## Bekannte Einschränkungen

### Kategorienfilter im Beitragseditor (⚠ in Bearbeitung)
Der Kategorienfilter schränkt die **Beitrags-Listenansicht** korrekt ein — die Rolle sieht nur Beiträge der erlaubten Kategorien. **Noch nicht funktionsfähig** ist jedoch die Einschränkung der Kategorie-Auswahl im Beitrags-/Seiten-Editor selbst: Beim Anlegen oder Bearbeiten eines Beitrags werden im Kategorien-Panel des Block-Editors (Gutenberg) noch alle vorhandenen Kategorien angezeigt, nicht nur die erlaubten. Die Auswahl einer nicht erlaubten Kategorie wird zwar nach dem Speichern serverseitig korrigiert, aber die Anzeige im Editor ist noch unvollständig eingeschränkt.

---

## Hinweise

### Login-Weiterleitung
Nach dem Login werden Benutzer (außer Admins) automatisch zur ersten für sie erlaubten Seite weitergeleitet — statt wie üblich zum Dashboard. Benutzer mit ausschließlich der Abonnenten-Rolle werden zum Frontend weitergeleitet.

### Abonnenten-Sperre
Benutzer, deren einzige Rolle „Abonnent/Subscriber" ist, werden vollständig aus dem WordPress-Backend ausgesperrt und zum Frontend umgeleitet — auch bei direktem URL-Aufruf.

### WordPress-Standardrollen
Die Plugin-Einstellungsseite zeigt eine Warnung wenn eine eingebaute WordPress-Rolle (Editor, Autor, Mitarbeiter, Abonnent) bearbeitet wird. Diese Rollen haben bereits vordefinierte Capabilities — Änderungen können deren normales Verhalten beeinflussen. Es wird empfohlen, für diesen Zweck **eigene benutzerdefinierte Rollen** zu erstellen (Tab „Rollen verwalten").

### Capability-Verwaltung
Das Plugin merkt sich welche Capabilities es einer Rolle hinzugefügt hat (`managed_caps`). Beim Entziehen von Menürechten werden nur diese selbst verwalteten Capabilities wieder entfernt — Capabilities die die Rolle bereits zuvor hatte, bleiben erhalten.

### Direktzugriff-Schutz
Das Entfernen von Menüpunkten ist nicht nur optisch: Das Plugin prüft bei jedem Backend-Seitenaufruf (`admin_init`) ob die Seite für die Rolle erlaubt ist und leitet andernfalls zum Dashboard um. Dabei werden auch `/wp-admin/post.php` (Beitrag/Seite bearbeiten) und Plugin-Seiten (`admin.php?page=...`) korrekt behandelt.

### Deinstallation
Beim **Löschen** des Plugins (nicht nur Deaktivieren) werden automatisch:
- Die Option `wp_userrights_permissions` aus der Datenbank entfernt
- Alle durch das Plugin vergebenen Capabilities von den Rollen zurückgenommen
- Alle plugin-erstellten Rollen von allen Benutzern entfernt und aus WordPress gelöscht

---

## Technische Details

| Datei | Funktion |
|---|---|
| `wp-userrights.php` | Bootstrap, Konstanten, Hook-Registrierung |
| `includes/class-admin-menu.php` | Menü-Enforcement (Priorität 9999), Direktzugriff-Schutz, Login-Redirect, Admin-Bar-Filter |
| `includes/class-settings.php` | Einstellungsseite (3 Tabs), Capability-Synchronisierung, Benutzer-Chip-UI |
| `includes/class-role-manager.php` | Rollen erstellen/löschen, AJAX-Rollenzuweisung |
| `includes/class-content-filter.php` | `pre_get_posts`-Filter für Admin & REST API, Mediathek-Filter |
| `uninstall.php` | Bereinigung bei Plugin-Löschung |
| `assets/admin.css` | Styles der Einstellungsseite |
| `assets/admin.js` | Interaktivität: Suche, Paginierung, Zähler, Capability-Vorschau, Chip-AJAX |

**WordPress-Optionen:**
- `wp_userrights_permissions` — Berechtigungen pro Rolle
- `wp_userrights_managed_roles` — Liste der plugin-erstellten Rollen

Gespeichertes Format:
```php
[
    'team1' => [
        'menu_slugs'         => ['edit.php', 'post-new.php', 'edit.php?post_type=page'],
        'allowed_categories' => ['team1'],
        'allowed_page_slugs' => ['team1'],
        'managed_caps'       => ['read', 'edit_posts', 'edit_pages'],
    ],
]
```

---

## Changelog

### 1.2.0
- Einstellungsseite läuft jetzt in voller Breite (kein max-width mehr)
- Tab „Benutzer": Toggle-Schalter pro Rolle durch Chip-UI ersetzt — skaliert auf beliebig viele Rollen
- Admin-Bar-Filter: Einträge ohne Zugriffsberechtigung werden ausgeblendet
- Mediathek-Einschränkung: Rollen sehen nur eigene hochgeladene Dateien
- Bulk-Rollenzuweisung für mehrere Benutzer gleichzeitig

### 1.1.0
- Login-Weiterleitung: Benutzer landen nach Login direkt auf der ersten erlaubten Seite
- Tab „Benutzer": Live-Suchfeld für Benutzer nach Name/E-Mail
- Tab „Benutzer": Paginierung (20 Benutzer pro Seite)
- README vollständig überarbeitet und erweitert

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
- Tab „Rollen verwalten": eigene Rollen erstellen und löschen
- Tab „Benutzer": Rollen per AJAX-Toggle-Schalter zuweisen
- Abonnenten-Sperre: kein Backend-Zugriff für reine Subscriber
- Saubere Deinstallationsroutine (inkl. plugin-erstellte Rollen)

---

*Entwickelt von [Friederich Loheide](https://loheide.eu)*
