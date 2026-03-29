# WP User Rights

**Autor:** Friederich Loheide  
**Website:** https://loheide.eu  
**Version:** 1.2.0  
**Erfordert WordPress:** 5.8+  
**Erfordert PHP:** 7.4+  
**Lizenz:** GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html  

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
Im Dropdown die gewünschte Rolle auswählen. Eigene (plugin-erstellte) Rollen sind gekennzeichnet.

#### Schritt 2 — Menüpunkte freigeben
Die Checkboxen der erlaubten Menüpunkte aktivieren. Die Baumstruktur zeigt Top-Level-Einträge und deren Untermenüs. Wird ein Untermenüpunkt aktiviert, wird der übergeordnete Eintrag automatisch mitgesetzt.

Die **Capability-Vorschau** unter der Toolbar zeigt in Echtzeit, welche WordPress-Capabilities beim Speichern an die Rolle vergeben werden.

#### Schritt 3 — Inhaltsfilter (optional)

| Feld | Beschreibung |
|---|---|
| **Nur Beiträge in Kategorien** | Kommagetrennte Kategorie-Slugs |
| **Nur Seiten mit diesen Slugs** | Kommagetrennte Seiten-Slugs |
| **Mediathek einschränken** | Nur eigene Medien |

#### Schritt 4 — Speichern
Auf **Einstellungen speichern** klicken.

---

### Tab „Rollen verwalten"

Neue Benutzerrollen direkt im Plugin erstellen.

#### Neue Rolle erstellen
1. Rollenname eingeben  
2. Slug prüfen/anpassen  
3. Rolle erstellen  

#### Rollen löschen
- Nur plugin-erstellte Rollen  
- Entfernt Rolle von allen Benutzern  
- Löscht alle Berechtigungen  

---

### Tab „Benutzer"

Benutzern zusätzliche Rollen zuweisen.

- Basis-Rolle bleibt erhalten  
- Plugin-Rollen werden additiv kombiniert  
- Verwaltung per Chip-UI  
- Suche nach Name/E-Mail  
- Paginierung ab 20 Nutzern  

---

## Beispiele

### Rolle „Team 1"
- Beiträge + Seiten bearbeiten  
- Kategorie: `team1`  
- Seite: `team1`  

### Rolle „Team 2"
- Nur bestimmtes Plugin-Menü  
- Seite: `team2`  

---

## Bekannte Einschränkungen

**Kategorienfilter im Editor:**  
Im Gutenberg-Editor werden aktuell noch alle Kategorien angezeigt. Einschränkung erfolgt erst serverseitig nach dem Speichern.

---

## Hinweise

### Login-Weiterleitung
Benutzer werden zur ersten erlaubten Seite geleitet.

### Abonnenten-Sperre
Subscriber ohne weitere Rollen haben keinen Backend-Zugriff.

### Standardrollen
Änderungen können bestehende Capabilities beeinflussen → eigene Rollen empfohlen.

### Capability-Verwaltung
Nur vom Plugin gesetzte Capabilities werden entfernt.

### Direktzugriff-Schutz
Backend-Zugriffe werden serverseitig geprüft und ggf. blockiert.

### Deinstallation
- Optionen gelöscht  
- Capabilities entfernt  
- Rollen gelöscht  

---

## Technische Details

| Datei | Funktion |
|---|---|
| `wp-userrights.php` | Bootstrap |
| `class-admin-menu.php` | Zugriff & Redirects |
| `class-settings.php` | UI & Logik |
| `class-role-manager.php` | Rollenverwaltung |
| `class-content-filter.php` | Inhaltsfilter |
| `uninstall.php` | Cleanup |

---

## Changelog

### 1.2.0
- UI verbessert  
- Chip-UI  
- Admin-Bar Filter  
- Mediathek-Filter  

### 1.1.0
- Login Redirect  
- Suche  
- Pagination  

### 1.0.0
- Initial Release  

---

## Lizenz

Dieses Plugin ist lizenziert unter der **GNU General Public License v2 oder später (GPLv2+)**.  
Details: https://www.gnu.org/licenses/gpl-2.0.html
