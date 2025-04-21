# Crocoblock Sync

Ein konfigurierbares WordPress-Plugin zur Synchronisation von Meta-Feldern mit Taxonomien für JetEngine/Crocoblock und ACF.

## Überblick

Crocoblock Sync ist ein leistungsstarkes WordPress-Plugin, das die Synchronisierung zwischen Meta-Feldern und den entsprechenden Taxonomien für Custom Post Types automatisiert. Es wurde speziell für die Verwendung mit JetEngine und Crocoblock entwickelt, funktioniert aber auch mit Advanced Custom Fields (ACF) und anderen Meta-Feld-Lösungen.

## Hauptfunktionen

* Automatische Synchronisation zwischen Meta-Feldern und Taxonomien
* Intuitiver "Synchronisieren & Speichern"-Button im Gutenberg/Elementor-Editor
* Alphabetische Sortierung der Taxonomie-Terme
* Option "Mehrere erlauben" für jede Taxonomie-Zuordnung
* Warnmeldungen bei mehreren ausgewählten Terms, wenn nicht erlaubt
* Unterstützung für beliebige Meta-Felder und Taxonomien
* Anpassbare Meldungen und Warnungen
* Kompatibilität mit JetEngine/Crocoblock und ACF

## Anforderungen

* WordPress 5.0 oder höher
* PHP 7.4 oder höher
* Kompatibel mit Gutenberg und Elementor

## Installation

1. Lade das Plugin als ZIP-Datei herunter
2. Gehe im WordPress-Admin zu "Plugins" > "Installieren" > "Plugin hochladen"
3. Wähle die ZIP-Datei aus und klicke auf "Jetzt installieren"
4. Aktiviere das Plugin

## Konfiguration

Nach der Aktivierung findest du die Einstellungen unter "Einstellungen" > "Crocoblock Sync". Hier kannst du:

### 1. Feld-Zuordnungen konfigurieren

Jede Zuordnung besteht aus folgenden Komponenten:

* **Meta-Feld**: Der Name des Meta-Felds (z.B. "reisethemen_meta")
* **Taxonomie**: Die zugehörige Taxonomie (z.B. "reisethemen")
* **Post-Typ**: Der Post-Typ, für den die Synchronisation gelten soll (z.B. "ir-tours")
* **Mehrere erlauben**: Option, die festlegt, ob mehrere Terme ausgewählt werden dürfen

### 2. Benutzerdefinierte Nachrichten anpassen

Du kannst folgende Texte anpassen:

* **Warnung bei mehreren Themen**: Meldung, die angezeigt wird, wenn mehrere Terme ausgewählt wurden
* **Text für Sync-Button**: Beschriftung des Synchronisations-Buttons
* **Erinnerung zum Synchronisieren**: Meldung, wenn versucht wird zu speichern ohne zu synchronisieren
* **Erfolgreiche Synchronisation**: Meldung nach erfolgreicher Synchronisation
* **Fehlermeldung bei Synchronisation**: Meldung, wenn die Synchronisation fehlschlägt

## Verwendung

Nach der Konfiguration wird bei jedem Beitrag des konfigurierten Post-Typs ein "Synchronisieren & Speichern"-Button im Editor angezeigt.

### Arbeitsablauf:

1. Bearbeite die Meta-Felder des Beitrags (z.B. wähle Reisethemen aus)
2. Klicke auf den "Synchronisieren & Speichern"-Button
   - Falls mehrere Terme ausgewählt wurden und "Mehrere erlauben" nicht aktiviert ist, wird eine Warnung angezeigt
3. Nach erfolgreicher Synchronisation wird der Beitrag automatisch gespeichert

**Wichtig**: Du musst immer zuerst synchronisieren, bevor du speicherst. Wenn du versuchst zu speichern, ohne vorher zu synchronisieren, wird eine Warnung angezeigt und der Speichervorgang verhindert.

## Technische Details

Das Plugin:
* Ermöglicht die Konfiguration mehrerer Meta-Feld-Taxonomie-Zuordnungen
* Unterstützt alphabetische Sortierung der Taxonomie-Terme
* Kann mit benutzerdefinierten Post-Typen verwendet werden
* Bietet einen anpassbaren Synchronisationsprozess
* Verwendet die WordPress-Ajax-API für die Synchronisation
* Ist mit Gutenberg und Elementor kompatibel

## Debug-Modus

Für Entwickler steht ein Debug-Modus zur Verfügung, der zusätzliche Informationen in der Browser-Konsole und im WordPress-Fehlerlog ausgibt. Der Debug-Modus kann in den Plugin-Einstellungen aktiviert werden.

## Entwickler

Joseph Kisler - Webwerkstatt  
Freiung 16/2/4, A-4600 Wels  
[web-werkstatt.at](https://web-werkstatt.at)

## Versionsverlauf

### 1.0
* Erstveröffentlichung
* Konfigurierbare Feld-Zuordnungen
* Anpassbare Meldungen
* Unterstützung für Gutenberg und Elementor

### 1.1
* Hinzufügung der "Mehrere erlauben"-Option für Taxonomien
* Verbesserte Benutzerführung mit Verhinderung des Speicherns ohne vorherige Synchronisation
* Optimierte Nachrichten-Handhabung
* Verbesserte Kompatibilität mit verschiedenen WordPress-Versionen

## Lizenz

BSD 3-Clause License

## Support

Für Support-Anfragen kontaktiere bitte den Entwickler unter info@web-werkstatt.at.
