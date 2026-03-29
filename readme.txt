=== WP User Rights ===
Contributors: friederichloheide
Tags: user roles, permissions, admin access, capabilities, backend control
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Granulare Steuerung von Backend-Zugriffsrechten pro Benutzerrolle inklusive Menü-, Inhalts- und Zugriffskontrolle.

== Description ==

WP User Rights ermöglicht die granulare Steuerung von Backend-Zugriffsrechten pro Benutzerrolle.

Administratoren sehen weiterhin alles — alle anderen Rollen sehen standardmäßig nur das Dashboard und erhalten dann gezielt Zugriff auf einzelne Menüpunkte.

Das Plugin liest alle im WordPress-Backend registrierten Menüpunkte dynamisch aus — inklusive der Menüs von anderen installierten Plugins — und stellt sie in einer Baumstruktur zur Konfiguration dar.

= Funktionen =

* Menü-Zugriffskontrolle pro Rolle (Top-Level & Submenüs)
* Automatische Capability-Vergabe und -Entzug
* Direktzugriff-Schutz für Admin-Seiten
* Login-Weiterleitung zur ersten erlaubten Seite
* Inhaltsfilter für Beiträge (Kategorien) und Seiten (Slugs)
* REST API / Gutenberg-Unterstützung
* Multi-Rollen-Unterstützung (Rechte werden kombiniert)
* Eigene Rollen erstellen und löschen
* Benutzer-Rollenverwaltung per AJAX (Chip-UI)
* Admin-Bar-Filter
* Mediathek-Einschränkung (nur eigene Uploads)
* Abonnenten-Sperre (kein Backend-Zugriff)
* Suchfunktion und Paginierung
* Live Capability-Vorschau
* Saubere Deinstallation

== Installation ==

1. Den Ordner `wp-userrights` in `wp-content/plugins/` hochladen
2. Plugin im WordPress-Backend aktivieren
3. Menüpunkt „Benutzerrechte“ öffnen

== Frequently Asked Questions ==

= Beeinflusst das Plugin bestehende Rollen? =

Ja. Änderungen an Standardrollen können bestehende Capabilities beeinflussen. Es wird empfohlen, eigene Rollen zu erstellen.

= Was passiert beim Entfernen von Rechten? =

Nur durch das Plugin hinzugefügte Capabilities werden entfernt. Bestehende bleiben erhalten.

= Wird direkter Zugriff auf Seiten verhindert? =

Ja. Nicht erlaubte Seiten werden serverseitig blockiert.

== Screenshots ==

1. Rollen- und Menüverwaltung
2. Benutzerverwaltung mit Chip-UI
3. Inhaltsfilter-Konfiguration

== Changelog ==

= 1.2.0 =
* Einstellungsseite volle Breite
* Benutzer-Tab: Chip-UI für Rollen
* Admin-Bar-Filter
* Mediathek-Einschränkung
* Bulk-Rollenzuweisung

= 1.1.0 =
* Login-Weiterleitung
* Benutzer-Suche
* Paginierung
* README überarbeitet

= 1.0.0 =
* Initial Release

== Upgrade Notice ==

= 1.2.0 =
Verbesserte Benutzerverwaltung und Medienfilter.

== Notes ==

Kategorienfilter im Editor derzeit eingeschränkt: Anzeige im Gutenberg-Panel ist noch nicht vollständig gefiltert, wird serverseitig korrigiert.

== Credits ==

Entwickelt von Friederich Loheide
https://loheide.eu
